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

class FeaturePacketCvController extends Controller
{
    use ResponseAPI;

    /**
     * Upload images and run the feature_packet_cv script.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function quantify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'white' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'red' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
            'surface_polarized' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
            'subsurface_polarized' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
            'woods_uv' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', $validator->errors()->all(), 422);
        }

        $tempFiles = [];

        try {
            // Store images temporarily
            $whitePath = $request->file('white')->store('tmp/feature_packet', 'public');
            $tempFiles[] = $whitePath;
            $absWhitePath = storage_path('app/public/' . $whitePath);

            $absRedPath = null;
            if ($request->hasFile('red')) {
                $redPath = $request->file('red')->store('tmp/feature_packet', 'public');
                $tempFiles[] = $redPath;
                $absRedPath = storage_path('app/public/' . $redPath);
            }

            $absSurfacePolarizedPath = null;
            if ($request->hasFile('surface_polarized')) {
                $spPath = $request->file('surface_polarized')->store('tmp/feature_packet', 'public');
                $tempFiles[] = $spPath;
                $absSurfacePolarizedPath = storage_path('app/public/' . $spPath);
            }

            $absSubsurfacePolarizedPath = null;
            if ($request->hasFile('subsurface_polarized')) {
                $sspPath = $request->file('subsurface_polarized')->store('tmp/feature_packet', 'public');
                $tempFiles[] = $sspPath;
                $absSubsurfacePolarizedPath = storage_path('app/public/' . $sspPath);
            }

            $absWoodsUvPath = null;
            if ($request->hasFile('woods_uv')) {
                $uvPath = $request->file('woods_uv')->store('tmp/feature_packet', 'public');
                $tempFiles[] = $uvPath;
                $absWoodsUvPath = storage_path('app/public/' . $uvPath);
            }

            $pythonPath = env('PYTHON_PATH', 'C:\\laragon\\bin\\python\\python-3.13\\python.exe');
            $scriptPath = base_path('AIA-Agent/feature_packet_cv.py');

            $command = [
                $pythonPath,
                $scriptPath,
                '--white', $absWhitePath,
            ];

            if ($absRedPath) {
                $command[] = '--red';
                $command[] = $absRedPath;
            }
            if ($absSurfacePolarizedPath) {
                $command[] = '--surface_polarized';
                $command[] = $absSurfacePolarizedPath;
            }
            if ($absSubsurfacePolarizedPath) {
                $command[] = '--subsurface_polarized';
                $command[] = $absSubsurfacePolarizedPath;
            }
            if ($absWoodsUvPath) {
                $command[] = '--woods_uv';
                $command[] = $absWoodsUvPath;
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
                Log::error('Feature Packet CV Output is not valid JSON', [
                    'output' => $output,
                    'error' => json_last_error_msg()
                ]);
                return $this->error('Error', ['Failed to parse script output'], 500);
            }

            return $this->success('Feature packet completed', $result);

        } catch (\Exception $e) {
            Log::error('Feature Packet CV Error', [
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
