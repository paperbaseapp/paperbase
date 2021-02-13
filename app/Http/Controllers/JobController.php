<?php


namespace App\Http\Controllers;


use App\Models\Library;
use App\Models\User;
use Imtigger\LaravelJobStatus\JobStatus;

class JobController extends Controller
{
    public function get($jobId)
    {
        $status = JobStatus::query()->whereKey($jobId)->firstOrFail();
        return response()->json($status);
    }

    public function getMultiple()
    {
        $data = $this->validateWith([
            'ids' => 'array|required',
            'ids.*' => 'string|required',
        ]);

        $jobs = [];
        foreach ($data['ids'] as $id) {
            $jobs[] = JobStatus::query()->whereKey($id)->firstOrFail();
        }

        return response()->json($jobs);
    }
}

