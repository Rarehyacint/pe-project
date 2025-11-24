<?php

namespace App\Jobs;

use App\Models\StudentPaper;
use App\Models\User;
use App\Services\ExamTakingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SubmitExpiredPaper implements ShouldQueue
{
    use Queueable;

    protected $studentPaper;
    protected $examTakingService;
    protected $user;
    public function __construct(StudentPaper $studentPaper, ExamTakingService $examTakingService, User $user)
    {
        $this->studentPaper = $studentPaper;
        $this->examTakingService = $examTakingService;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        if ($this->studentPaper->isSubmitted()) {
            Log::info('paper is submitted');
            return;
        }

        // Check expiration
        if ($this->studentPaper->isExpired()) {
            Log::info('paper has expired');
            $this->studentPaper->status = "auto_completed";
            $this->examTakingService->submitPaper($this->studentPaper, $this->user); 
            return;
        }

    }
}
