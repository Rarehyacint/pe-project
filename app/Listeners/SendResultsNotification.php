<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\ExamResultsPublished;
use App\Models\Notification;

class SendResultsNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ExamResultsPublished $event): void
    {
                $exam = $event->exam;

        // Notify all students enrolled in this exam
        foreach ($exam->users as $student) {
            $notif = Notification::create([
                'title'   => 'Results Published',
                'message' => "Your results for {$exam->name} are now available.",
                'is_public' => false,
            ]);
            $notif->users()->attach($student->id);
        }

        // Notify professors that results are released
        $profNotif = Notification::create([
            'title'   => 'Exam Results Released',
            'message' => "Results for {$exam->name} have been published.",
            'is_public' => false,
        ]);
        $profNotif->roles()->attach([1,2,3,4]);       
        //superadmin role_id = 1
        //faculty role_id = 2
        //department head role_id = 3
        //college dean role_id = 4

    }
}
