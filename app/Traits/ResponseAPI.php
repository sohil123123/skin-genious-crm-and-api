<?php

namespace App\Traits;

trait ResponseAPI
{
    /**
     * Core of response
     *
     * @param   string          $message
     * @param   array|object    $data
     * @param   integer         $statusCode
     * @param   boolean         $isSuccess
     */
    public function coreResponse($message = null, $data = null, $statusCode = null, $isSuccess = true)
    {
        // Check the params
        if(!$message) return response()->json(['message' => 'Unauthorized', 'error' => false, 'code' => HTTP_UNAUTHORIZED, 'results' => []], HTTP_UNAUTHORIZED);

        $statusCode = $statusCode ?? HTTP_BAD_REQUEST;
        // Send the response
        if($isSuccess) {
            return response()->json([
                'message' => $message,
                'error' => false,
                'code' => $statusCode,
                'results' => $data
            ], $statusCode);
        } else {
            return response()->json([
                'message' => $message,
                'error' => true,
                'code' => $statusCode,
                'results' => $data
            ], $statusCode);
        }
    }

    /**
     * Send any success response
     *
     * @param   string          $message
     * @param   array|object    $data
     * @param   integer         $statusCode
     */
    public function success($message = null, $data = [], $statusCode = 200)
    {
        return $this->coreResponse($message, $data, $statusCode);
    }

    /**
     * Send any error response
     *
     * @param   string          $message
     * @param   integer         $statusCode
     */
    public function error($message = null, $data = [], $statusCode = 500)
    {
        return $this->coreResponse($message, $data, $statusCode, false);
    }
}
?>
