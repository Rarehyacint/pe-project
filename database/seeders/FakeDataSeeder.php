<?php

namespace Database\Seeders;

use App\Factories\QuestionFactory as OwnQuestionFactory;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionTypeService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Storage;

class FakeDataSeeder extends Seeder
{
    protected $question_levels;
    public function __construct()
    {
        $this->question_levels = [
            'remember',
            'understand',
            'apply',
            'analyze',
            'evaluate',
            'create'
        ];
    }

    public function run(): void
    {
        foreach ($this->question_levels as $level){
            Tag::firstOrCreate(['name' => $level]);
        }

        $dataPath = database_path('seeders/QuestionSeederJSON');
        $files = glob($dataPath . '/*.json'); 

        foreach ($files as $file) {
            $subject_data = json_decode(file_get_contents($file), true);

            $subject = Subject::firstOrCreate([
                'name' => $subject_data['name'],
                'code' => $subject_data['code'],
                'year_level' => $subject_data['year_level'],
                'created_at' => Carbon::now()
            ]);
            $subject->courses()->attach([1, 2]);
            
            foreach ($subject_data['topics'] as $topic_data) {
                $topic = Topic::firstOrCreate([
                    'name' => $topic_data['name'],
                    'subject_id' => $subject->id,
                    'created_at' => Carbon::now()
                ]);


                foreach ($topic_data['questions'] ?? [] as $question_data) {
                    $exists = Question::where('name', $question_data['name'])
                        ->where('topic_id', $topic->id)
                        ->exists();

                    if (! $exists) {
                        $question_data['topic'] = $topic->id;
                        $super_admin_id = User::role('super_admin')->first()->id;
                        $question_factory = new OwnQuestionFactory();
                        $question_factory->createFakeData($question_data, $super_admin_id);
                    }
                }
            }
        }
    }
}
