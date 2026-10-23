<?php

use Mpdf\Mpdf;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringEndsWith;
use function PHPUnit\Framework\assertStringNotContainsString;

final class BreadContentGeneratorTest extends TestCase
{
    private function getConfiguration(string $config): array
    {
        $json = file_get_contents('tests/configurations/' . $config . ".json");
        return json_decode($json, true);
    }
    private function getServiceBodies(string $service_bodies): array
    {
        $json = file_get_contents('tests/serviceBodies/' . $service_bodies . ".json");
        return json_decode($json, true);
    }
    private function getFormats($formats)
    {
        $json = file_get_contents('tests/formats/' . $formats . ".json");
        return json_decode($json, true)['formats'];
    }
    public function testServiceBodyShortcodes()
    {
        $this->doTest(
            'berlin-booklet',
            [],
            'berlin-service-bodies',
            [
                '[service_body]' => 'GEBIET BERLIN',
                '[service_body_name]' => 'Gebiet Berlin',
                '[service_body_description]' => 'Zuständig für: Berlin und Umland',
                '[service_body_helpline]' => '+49 30 12345678',
                '[service_body_1]' => 'GEBIET BERLIN',
                '[service_body_1_name]' => 'Gebiet Berlin',
                '[service_body_1_description]' => 'Zuständig für: Berlin und Umland',
                '[service_body_1_helpline]' => '+49 30 12345678',
                '[service_body_2]' => 'Not Used',
                '[service_body_2_name]' => 'Not Used',
                '[service_body_2_description]' => 'Not Used',
                '[service_body_2_helpline]' => 'Not Used',
                '[service_body_5]' => 'Not Used',
                '[service_body_5_name]' => 'Not Used',
                '[service_body_5_description]' => 'Not Used',
                '[service_body_5_helpline]' => 'Not Used',
            ]
        );
    }
    public function testServiceBodyShortcodesMultiple()
    {
        $this->doTest(
            'berlin-booklet',
            ['service_bodies' => [
                'Gebiet Süd,7,1,Deutschsprachige Region',
                'Gebiet Österreich,10,1,Deutschsprachige Region',
                'Online Gebiet,12,1,Deutschsprachige Region',
            ]],
            'berlin-service-bodies',
            [
                '[service_body_1]' => 'GEBIET SÜD',
                '[service_body_1_name]' => 'Gebiet Süd',
                '[service_body_1_description]' => 'Zuständig für: Stuttgart, Bayern, Österreich',
                '[service_body_1_helpline]' => '0800 4453362',
                '[service_body_2]' => 'GEBIET ÖSTERREICH',
                '[service_body_2_name]' => 'Gebiet Österreich',
                '[service_body_2_description]' => 'Zuständig für: Österreich',
                '[service_body_2_helpline]' => '',
                '[service_body_3]' => 'ONLINE GEBIET',
                '[service_body_3_name]' => 'Online Gebiet',
                '[service_body_3_description]' => '',
                '[service_body_3_helpline]' => '',
                '[service_body_4]' => 'Not Used',
            ]
        );
    }
    public function testServiceBodyMissingFromBmlt()
    {
        $this->doTest(
            'berlin-booklet',
            ['service_bodies' => ['Gebiet Gelöscht,99,1,Deutschsprachige Region']],
            'berlin-service-bodies',
            [
                '[service_body_1]' => 'GEBIET GELÖSCHT',
                '[service_body_1_name]' => 'Gebiet Gelöscht',
                '[service_body_1_description]' => '',
                '[service_body_1_helpline]' => '',
            ]
        );
    }
    private function replaceFormatShortcodes(string $data): string
    {
        $generator = $this->makeGenerator('berlin-booklet', ['custom_section_font_size' => 7.7, 'custom_section_line_height' => 1], 'berlin-service-bodies');
        (new ReflectionMethod($generator, 'replace_format_shortcodes'))->invokeArgs($generator, [&$data, 'custom_section']);
        return $data;
    }
    public function testFormatShortcodeAtStart()
    {
        $data = $this->replaceFormatShortcodes('[format_codes_used_basic]<p>after</p>');
        assertStringNotContainsString('[format_codes_used_basic]', $data);
        assertEquals(1, substr_count($data, '<table'));
        assertStringEndsWith('<p>after</p>', $data);
    }
    public function testFormatShortcodeRepeated()
    {
        $data = $this->replaceFormatShortcodes('<p>[format_codes_used_basic]</p><p>[format_codes_used_basic]</p>');
        assertStringNotContainsString('[format_codes_used_basic]', $data);
        assertEquals(2, substr_count($data, '<table'));
    }
    public function testFormatShortcodeFontSize()
    {
        $data = $this->replaceFormatShortcodes('<p>[format_codes_used_basic]</p><p>[format_codes_used_detailed]</p>');
        assertStringContainsString('font-size:7.7pt;', $data);
        assertStringNotContainsString('ptpt', $data);
    }
    public function testFormatShortcodeLarge()
    {
        $data = $this->replaceFormatShortcodes('<p>[format_codes_used_basic_large]</p>');
        assertEquals(50, substr_count($data, '<tr>'));
    }
    private function makeGenerator($config, $changes, $serviceBodiesJson): Bread_ContentGenerator
    {
        $options = $this->getConfiguration($config);
        foreach ($changes as $key => $value) {
            $options[$key] = $value;
        }
        $bread = new Bread($options);
        $bread->bmlt()->setFormatBase('german-formats');
        $bread->bmlt()->set_service_bodies($this->getServiceBodies($serviceBodiesJson));
        $formats = $this->getFormats('berlin-formats-de');
        $formatMgr = new Bread_FormatsManager($formats, 'de', $bread->bmlt());

        return new Bread_ContentGenerator($this->createMock(Mpdf::class), $bread, [], $formatMgr);
    }
    public function doTest($config, $changes, $serviceBodiesJson, $expected): void
    {
        $generator = $this->makeGenerator($config, $changes, $serviceBodiesJson);
        $shortcodes = (new ReflectionProperty($generator, 'shortcodes'))->getValue($generator);
        foreach ($expected as $shortcode => $value) {
            assertEquals($value, $shortcodes[$shortcode], $shortcode);
        }
    }
}
