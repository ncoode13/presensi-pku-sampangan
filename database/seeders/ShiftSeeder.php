<?php
namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Shift::create(
            [
                'name'                    => 'Dayoff',
                'start_time'              => '00:00:00',
                'end_time'                => '00:00:00',
                'tolerance_come_too_late' => 0,
                'tolerance_go_home_early' => 0,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Reguler',
                'start_time'              => '08:00:00',
                'end_time'                => '15:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi 1',
                'start_time'              => '07:00:00',
                'end_time'                => '14:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi 2.1',
                'start_time'              => '07:00:00',
                'end_time'                => '14:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi 2.2',
                'start_time'              => '06:00:00',
                'end_time'                => '13:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi 3.1',
                'start_time'              => '07:00:00',
                'end_time'                => '14:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Middle A',
                'start_time'              => '08:30:00',
                'end_time'                => '15:30:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,

            ],
        );
        Shift::create(
            [
                'name'                    => 'Middle B',
                'start_time'              => '09:00:00',
                'end_time'                => '16:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Middle C',
                'start_time'              => '10:00:00',
                'end_time'                => '17:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Middle D',
                'start_time'              => '11:00:00',
                'end_time'                => '18:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Middle E',
                'start_time'              => '12:00:00',
                'end_time'                => '19:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Siang 2.1',
                'start_time'              => '14:00:00',
                'end_time'                => '21:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Siang 2.2',
                'start_time'              => '13:00:00',
                'end_time'                => '20:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Siang 3.1',
                'start_time'              => '14:00:00',
                'end_time'                => '20:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Malam 3.1',
                'start_time'              => '20:00:00',
                'end_time'                => '07:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Siang 2.3',
                'start_time'              => '12:00:00',
                'end_time'                => '19:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi 3.2',
                'start_time'              => '07:00:00',
                'end_time'                => '14:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Malam 3.2',
                'start_time'              => '21:00:00',
                'end_time'                => '07:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'PS',
                'start_time'              => '07:00:00',
                'end_time'                => '20:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'PS Dokter/Satpam',
                'start_time'              => '07:00:00',
                'end_time'                => '21:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Reg-Jaga Siang Dokter',
                'start_time'              => '08:00:00',
                'end_time'                => '21:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi 7.30',
                'start_time'              => '07:30:00',
                'end_time'                => '14:30:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Siang dan Malam',
                'start_time'              => '14:00:00',
                'end_time'                => '07:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi dan Malam',
                'start_time'              => '07:00:00',
                'end_time'                => '06:59:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Malam CSSD',
                'start_time'              => '22:00:00',
                'end_time'                => '05:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Sore CSSD',
                'start_time'              => '15:00:00',
                'end_time'                => '22:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Pagi Gizi',
                'start_time'              => '05:00:00',
                'end_time'                => '12:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Middle Siang',
                'start_time'              => '16:00:00',
                'end_time'                => '22:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
        Shift::create(
            [
                'name'                    => 'Reguler-Malam Dokter',
                'start_time'              => '08:00:00',
                'end_time'                => '07:00:00',
                'tolerance_come_too_late' => 10,
                'tolerance_go_home_early' => 5,
            ],
        );
    }
}
