<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Http;

class AutoCaptureController extends BaseApiController
{
    private $DEVICE = "192.168.31.177:5555";
    private $PACKAGE = "com.yiyuan.skin";
    private $CAMERA_ACTIVITY = "com.yiyuan.skin/.ui.activity.CameraActivity";
    private $REMOTE_ROOT = "/sdcard/yiyuan/image";

    private $AI_TAP_X = 539;
    private $AI_TAP_Y = 1789;
    private $CAPTURE_WAIT_SECONDS = 14;

    // public function connect(Request $request)
    // {
    //     $pythonPath = "python3";
    //     $scriptPath = base_path("python/adb_connect.py");

    //     // run python + capture output + errors
    //     $cmd = "$pythonPath \"$scriptPath\" 2>&1";

    //     $output = shell_exec($cmd);

    //     if (empty($output)) {
    //         return response()->json([
    //             "success" => false,
    //             "message" => "Python script returned no output. Maybe shell_exec is disabled or Python not found.",
    //         ], 500);
    //     }

    //     // convert Python JSON into Laravel JSON
    //     $data = json_decode($output, true);

    //     if (!$data) {
    //         return response()->json([
    //             "success" => false,
    //             "message" => "Invalid JSON returned from Python",
    //             "raw_output" => $output
    //         ], 500);
    //     }

    //     return response()->json($data);
    // }

    public function capturePhotos(Request $request)
    {
        // // $url = "http://127.0.0.1:5005/run-local";
        // $url = "https://aiaesthetics-agent.cbphysiotherapy.in/run-local";

        // $response = Http::withHeaders([
        //     'X-API-KEY' => '2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE',
        // ])
        // ->withoutVerifying()
        // ->get($url);

        // return $response->json();

        // Cloudflare Tunnel URL
        $endpoint = "https://amdermatology-agent.drshehlaendocrinologist.com/auto-capture-process";
        try {
            // Send POST request to Python/ADB server
            // $response = Http::withoutVerifying()->post($endpoint);
            $response = Http::withHeaders([
                'X-API-KEY' => '2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE',
            ])
            ->withoutVerifying()
            ->get($endpoint);

            if ($response->failed()) {
                return $this->error('error.', [$response->body()], HTTP_NOT_FOUND);
            }

            $data = $response->json();

            if ($data["status"] !== "success") {
                return $this->error('error.', [$data["output"]], HTTP_NOT_FOUND);
            }

            return $this->success('Capture successfully triggered!', $data);
        } catch (\Exception $e) {
            return $this->error('error.', ['Could not contact device server: ' . $e->getMessage()], HTTP_NOT_FOUND);
        }
    }
}
