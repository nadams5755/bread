<?php

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;

final class BreadMeetingEnhancerTest extends TestCase
{
    protected function setUp(): void
    {
        // WordPress forces UTC; the enhancer relies on it.
        date_default_timezone_set('UTC');
    }
    private function getConfiguration(string $config): array
    {
        $json = file_get_contents('tests/configurations/' . $config . ".json");
        return json_decode($json, true);
    }
    private function getFormats($formats): array
    {
        $json = (new WP_Filesystem_Direct(null))->get_contents('tests/formats/' . $formats . ".json");
        return json_decode($json, true)['formats'];
    }
    private function enhance(array $changes, array $meeting, string $lang = 'de', array $areas = []): array
    {
        $options = $this->getConfiguration('berlin-booklet');
        $options['weekday_language'] = $lang;
        foreach ($changes as $key => $value) {
            $options[$key] = $value;
        }
        $bread = new Bread($options);
        $bread->bmlt()->setFormatBase('german-formats');
        $formats = $this->getFormats('berlin-formats-de');
        $mgr = new Bread_FormatsManager($formats, $lang, $bread->bmlt());
        $meeting = array_merge([
            'start_time' => '19:30:00',
            'duration_time' => '01:00:00',
            'weekday_tinyint' => '2',
            'service_body_bigint' => '3',
            'format_shared_id_list' => '213',
        ], $meeting);
        return (new Bread_Meeting_Enhancer($bread, $areas))->enhance_meeting($meeting, $lang, $mgr);
    }
    private function startTime(array $changes, array $meeting, string $lang = 'de'): string
    {
        return $this->enhance($changes, $meeting, $lang)['start_time'];
    }
    public function testDuration()
    {
        $m = $this->enhance([], ['duration_time' => '01:30:00']);
        assertEquals(90, $m['duration_m']);
        assertEquals('1.5', $m['duration_h']);
        $m = $this->enhance([], ['duration_time' => '01:00:00']);
        assertEquals(60, $m['duration_m']);
        assertEquals('1', $m['duration_h']);
    }
    public function testStartTime24()
    {
        $opts = ['time_option' => 1, 'time_clock' => '24', 'remove_space' => false];
        assertEquals('19:30', $this->startTime($opts, []));
        assertEquals('09:05', $this->startTime($opts, ['start_time' => '09:05:00']));
    }
    public function testStartTime24fr()
    {
        $opts = ['time_option' => 1, 'time_clock' => '24fr', 'remove_space' => false];
        assertEquals('19h30', $this->startTime($opts, []));
    }
    public function testStartTime12()
    {
        $opts = ['time_option' => 1, 'time_clock' => '12', 'remove_space' => false];
        assertEquals('7:30 PM', $this->startTime($opts, []));
        assertEquals('NOON', $this->startTime($opts, ['start_time' => '12:00:00']));
        $opts['remove_space'] = true;
        assertEquals('7:30PM', $this->startTime($opts, []));
        assertEquals('NOON', $this->startTime($opts, ['start_time' => '12:00:00']));
    }
    public function testStartAndEndTime()
    {
        $opts = ['time_option' => 2, 'time_clock' => '24', 'remove_space' => false];
        assertEquals('19:30 - 20:30', $this->startTime($opts, []));
        $opts['remove_space'] = true;
        assertEquals('19:30-20:30', $this->startTime($opts, []));
        $opts = ['time_option' => 2, 'time_clock' => '12', 'remove_space' => false];
        assertEquals('11:00 AM - NOON', $this->startTime($opts, ['start_time' => '11:00:00']));
    }
    public function testCompactStartAndEndTime()
    {
        $opts = ['time_option' => 3, 'time_clock' => '12', 'remove_space' => false];
        assertEquals('7:30 - 8:30 PM', $this->startTime($opts, []));
        assertEquals('7 - 8 PM', $this->startTime($opts, ['start_time' => '19:00:00']));
        assertEquals('11 - NOON', $this->startTime($opts, ['start_time' => '11:00:00']));
        assertEquals('NOON - 1 PM', $this->startTime($opts, ['start_time' => '12:00:00']));
    }
    public function testPersian()
    {
        $opts = ['time_option' => 1, 'time_clock' => '24', 'remove_space' => false];
        assertEquals('۱۹:۳۰', $this->startTime($opts, [], 'fa'));
        $opts['time_option'] = 2;
        assertEquals('۲۰:۳۰ - ۱۹:۳۰', $this->startTime($opts, [], 'fa'));
        $opts = ['time_option' => 1, 'time_clock' => '12', 'remove_space' => false];
        assertEquals('ظهر', $this->startTime($opts, ['start_time' => '12:00:00'], 'fa'));
    }
    public function testDay()
    {
        $m = $this->enhance([], ['weekday_tinyint' => '2']);
        assertEquals('Montag', $m['day']);
        assertEquals('Mo', $m['day_abbr']);
    }
    public function testAreaName()
    {
        $areas = ['Gebiet Süd,7,1,x', 'Gebiet Berlin,3,1,x'];
        $m = $this->enhance([], [], 'de', $areas);
        assertEquals('Gebiet Berlin', $m['area_name']);
        assertEquals('G', $m['area_i']);
        $m = $this->enhance([], ['service_body_bigint' => '99'], 'de', $areas);
        assertEquals('', $m['area_name']);
        assertEquals('', $m['area_i']);
    }
    public function testWheelchair()
    {
        $m = $this->enhance(['wheelchair_size' => '20px'], ['format_shared_id_list' => '213,33']);
        assertEquals('<img src="./css/wheelchair.png" width="20px" height="20px">', $m['wheelchair']);
        $m = $this->enhance([], ['format_shared_id_list' => '213']);
        assertEquals('', $m['wheelchair']);
    }
}
