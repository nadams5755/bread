<?php

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertStringContainsString;

final class BreadFormatsManagerTest extends TestCase
{
    private function getFormats($formats): array
    {
        $json = file_get_contents('tests/formats/' . $formats . ".json");
        return json_decode($json, true)['formats'];
    }
    private function getFormatMgr($usedFormat, $lang, $bmlt)
    {
        $x = $this->getFormats($usedFormat);
        return new Bread_FormatsManager($x, $lang, $bmlt);
    }
    public function testGetFormatsUsed()
    {
        $bread = new Bread([]);
        $bread->bmlt()->setFormatBase('german-formats');
        $mgr = $this->getFormatMgr('berlin-formats-de', 'de', $bread->bmlt());
        $used = $mgr->getFormatsUsed();
        assertEquals(50, count($used));
        $o1 = $mgr->getFormatByKey('de', 'O1');
        assertNotNull($o1);
        $o1e = $mgr->getFormatByKey('en', 'O1');
        assertNotNull($o1e);
        $used = $mgr->getFormatsUsed('en');
        assertEquals(50, count($used));
    }
    private function newMgr(int $limit = 0): Bread_FormatsManager
    {
        $bread = new Bread([]);
        $bread->bmlt()->setFormatBase('german-formats');
        $x = $this->getFormats('berlin-formats-de');
        if ($limit > 0) {
            $x = array_slice($x, 0, $limit);
        }
        return new Bread_FormatsManager($x, 'de', $bread->bmlt());
    }
    public function testWriteFormatsTwoPerRow()
    {
        $html = $this->newMgr()->write_formats('de', false, '1.2', '8');
        assertEquals(25, substr_count($html, '<tr>'));
        assertStringContainsString("font-size:8pt;", $html);
    }
    public function testWriteFormatsTwoPerRowOddCount()
    {
        $html = $this->newMgr(3)->write_formats('de', false, '1.2', '8');
        assertEquals(2, substr_count($html, '<tr>'));
        foreach (['ru', 'Med', 'BT'] as $key) {
            assertStringContainsString('>' . $key . '</td>', $html);
        }
    }
    public function testWriteFormatsOnePerRow()
    {
        $html = $this->newMgr(3)->write_formats('de', false, '1.2', '8', false);
        assertEquals(3, substr_count($html, '<tr>'));
        foreach (['ru', 'Med', 'BT'] as $key) {
            assertStringContainsString('>' . $key . '</td>', $html);
        }
        $html = $this->newMgr()->write_formats('de', false, '1.2', '8', false);
        assertEquals(50, substr_count($html, '<tr>'));
    }
    public function testWriteFormatsAll()
    {
        $html = $this->newMgr()->write_formats('de', true, '1.2', '8', false);
        assertEquals(73, substr_count($html, '<tr>'));
    }
    public function testWriteDetailedFormats()
    {
        $html = $this->newMgr()->write_detailed_formats('de', false, '1.2', '8');
        assertEquals(50, substr_count($html, '<tr>'));
        assertStringContainsString('(Russisch) In russischer Sprache', $html);
    }
    public function testWriteDetailedFormatsAll()
    {
        $html = $this->newMgr()->write_detailed_formats('de', true, '1.2', '8');
        assertEquals(73, substr_count($html, '<tr>'));
    }
    public function testWheelchairFormat()
    {
        assertEquals('Beh', $this->newMgr()->getWheelchairFormat('de')['key_string']);
    }
}
