<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Traits\ResponseAPI;

class VisionQuantifierController extends Controller
{
    use ResponseAPI;

    /**
     * Upload images and run the vision quantifier script.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function quantify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uv' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'positive' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'white' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'blue' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', $validator->errors()->all(), 422);
        }

        $tempFiles = [];

        try {
            // Store images temporarily
            $uvPath = $request->file('uv')->store('tmp/vision', 'public');
            $tempFiles[] = $uvPath;

            $positivePath = $request->file('positive')->store('tmp/vision', 'public');
            $tempFiles[] = $positivePath;

            $whitePath = $request->file('white')->store('tmp/vision', 'public');
            $tempFiles[] = $whitePath;

            $bluePath = null;
            if ($request->hasFile('blue')) {
                $bluePath = $request->file('blue')->store('tmp/vision', 'public');
                $tempFiles[] = $bluePath;
            }

            // Get absolute paths for the script
            $absUvPath = storage_path('app/public/' . $uvPath);
            $absPositivePath = storage_path('app/public/' . $positivePath);
            $absWhitePath = storage_path('app/public/' . $whitePath);
            $absBluePath = $bluePath ? storage_path('app/public/' . $bluePath) : null;

            $pythonPath = config('project.python_path');
            $scriptPath = base_path('AIA-Agent/iv_vision_quantifier.py');

            $command = [
                $pythonPath,
                $scriptPath,
                '--uv', $absUvPath,
                '--positive', $absPositivePath,
                '--white', $absWhitePath,
            ];

            if ($absBluePath) {
                $command[] = '--blue';
                $command[] = $absBluePath;
            }

            $process = new Process($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();

            // Extract JSON from output (in case there are log prints)
            $jsonStart = strpos($output, '{');
            $jsonEnd = strrpos($output, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonContent = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
                $result = json_decode($jsonContent, true);
            } else {
                $result = null;
            }

            if (json_last_error() !== JSON_ERROR_NONE || $result === null) {
                Log::error('Vision Quantifier Output is not valid JSON', [
                    'output' => $output,
                    'error' => json_last_error_msg()
                ]);
                return $this->error('Error', ['Failed to parse script output'], 500);
            }

            return $this->success('Vision quantification completed', $result);

        } catch (\Exception $e) {
            Log::error('Vision Quantifier Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Error', [$e->getMessage()], 500);
        } finally {
            // Cleanup temp files
            foreach ($tempFiles as $file) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
