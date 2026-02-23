<?php

namespace Database\Seeders;

use App\Models\IptvSeed;
use Illuminate\Database\Seeder;

class IptvSeedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Очищаем таблицу перед наполнением, чтобы пересоздать сидеры с нуля
        IptvSeed::truncate();

        $seeds = [
            [
                'name' => 'Плейлист ТВ',
                'url' => 'https://raw.githubusercontent.com/Dimonovich/TV/Dimonovich/FREE/TV',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Сборник ТВ',
                'url' => 'https://raw.githubusercontent.com/Voxlist/voxlist/refs/heads/main/voxlist.m3u',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'smolnp.github.io ТВ',
                'url' => 'https://smolnp.github.io/IPTVru//IPTVstable.m3u8',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Страны мира (EU/TR/US/RU/KZ/BY/TH)',
                'url' => 'https://iptv-org.github.io/iptv/index.country.m3u',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'name' => 'TV ALL list ru',
                'url' => 'https://raw.githubusercontent.com/naggdd/iptv/main/ru.m3u',
                'sort_order' => 50,
                'is_active' => true,
            ],
             [
                'name' => 'FreeTV_World',
                'url' => 'https://raw.githubusercontent.com/iprtl/m3u/live/Freetv.m3u',
                'sort_order' => 60,
                'is_active' => true,
            ],
        ];

        foreach ($seeds as $seed) {
            IptvSeed::create($seed);
        }
    }
}
