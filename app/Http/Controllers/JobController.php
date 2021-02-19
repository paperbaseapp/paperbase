<?php


namespace App\Http\Controllers;


use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Imtigger\LaravelJobStatus\JobStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function getMultipleBatches()
    {
        $data = $this->validateWith([
            'ids' => 'array|required',
            'ids.*' => 'string|required',
        ]);

        $batches = [];
        foreach ($data['ids'] as $id) {
            if ($batch = Bus::findBatch($id)) {
                $batches[] = $batch;
            } else {
                throw new NotFoundHttpException('Batch ' . $id . ' not found');
            }
        }

        return response()->json($batches);
    }
}

