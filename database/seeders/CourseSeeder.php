<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Course::create([
            'name' => 'Bachelor of Science in Computer Science',
            'abbreviation' => 'BSCS'
        ]);
        Course::create([
            'name' => 'Bachelor of Science in Computer Engineering',
            'abbreviation' => 'BSCPE'
        ]);        
        Course::create([
            'name' => 'Bachelor of Science in Information Technology',
            'abbreviation' => 'BSIT'
        ]);        
        Course::create([
            'name' => 'Bachelor of Multimedia Arts',
            'abbreviation' => 'BMMA'
        ]);
    }
}
