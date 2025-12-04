import subprocess, time, os, datetime, pathlib

# ---------------------------------------------------------
# CONFIGURATION
# ---------------------------------------------------------

DEVICE = "192.168.31.177:5555"
PACKAGE = "com.yiyuan.skin"
CAMERA_ACTIVITY = "com.yiyuan.skin/.ui.activity.CameraActivity"

REMOTE_ROOT = "/sdcard/yiyuan/image"
LOCAL_ROOT = pathlib.Path.home() / "Desktop" / "revised AIA database"

AI_TAP_X = 539
AI_TAP_Y = 1789

CAPTURE_WAIT_SECONDS = 14


# ---------------------------------------------------------
# HELPERS + SAFE ERROR HANDLING
# ---------------------------------------------------------

class CaptureError(Exception):
    """Custom exception for controlled capture errors."""
    def __init__(self, code, message, details=None):
        self.code = code
        self.message = message
        self.details = details
        super().__init__(message)


def run(cmd):
    try:
        return subprocess.check_output(cmd, shell=True, text=True)
    except subprocess.CalledProcessError as e:
        raise CaptureError(
            code="ADB_COMMAND_FAILED",
            message=f"ADB command failed: {cmd}",
            details=str(e)
        )


def adb(cmd):
    return run(f"adb -s {DEVICE} {cmd}")


def connect_device():
    try:
        out = run(f"adb connect {DEVICE}")
        if "connected" not in out.lower():
            raise CaptureError(
                code="DEVICE_CONNECTION_FAILED",
                message="Could not connect to scanning device.",
                details=out
            )
        time.sleep(1)
    except Exception as e:
        raise CaptureError(
            code="DEVICE_CONNECTION_FAILED",
            message="Failed to connect to scanning device.",
            details=str(e)
        )


def open_camera():
    try:
        adb(f"shell am start -n {CAMERA_ACTIVITY}")
        time.sleep(2)
    except Exception as e:
        raise CaptureError(
            code="CAMERA_LAUNCH_FAILED",
            message="Unable to launch camera on device.",
            details=str(e)
        )


def trigger_ai_capture():
    try:
        adb(f"shell input tap {AI_TAP_X} {AI_TAP_Y}")
        time.sleep(CAPTURE_WAIT_SECONDS)
    except Exception as e:
        raise CaptureError(
            code="CAPTURE_TRIGGER_FAILED",
            message="Failed to trigger AI 6-light skin capture.",
            details=str(e)
        )


def get_latest_timestamp(today):
    try:
        result = adb(f"shell ls -t {REMOTE_ROOT}/{today}")
        files = result.strip().split("\n")

        if not files or files == ['']:
            raise CaptureError(
                code="NO_TIMESTAMP_FOUND",
                message="No image folder detected on device.",
                details="Device returned empty directory list."
            )

        ts = files[0].split("-")[0]
        return ts

    except CaptureError:
        raise
    except Exception as e:
        raise CaptureError(
            code="TIMESTAMP_LOOKUP_FAILED",
            message="Failed to read timestamp folder from device.",
            details=str(e)
        )


def pull_images(today, ts):
    try:
        local_dir = LOCAL_ROOT / today / ts
        local_dir.mkdir(parents=True, exist_ok=True)

        mapping = {
            f"{ts}-image.jpg": "white.jpg",
            f"{ts}-image_positive.jpg": "positive.jpg",
            f"{ts}-image_negative.jpg": "negative.jpg",
            f"{ts}-image_uv.jpg": "uv.jpg",
            f"{ts}-image_woods.jpg": "woods.jpg",
            f"{ts}-image_blue.jpg": "blue.jpg"
        }

        for original, new in mapping.items():
            remote_path = f"{REMOTE_ROOT}/{today}/{original}"
            adb(f"pull {remote_path} '{local_dir}'")

            old_file = local_dir / original
            new_file = local_dir / new

            if old_file.exists():
                old_file.rename(new_file)
            else:
                raise CaptureError(
                    code="IMAGE_MISSING",
                    message=f"Expected image missing: {original}",
                    details=f"{remote_path} not found on device."
                )

        return local_dir

    except CaptureError:
        raise
    except Exception as e:
        raise CaptureError(
            code="IMAGE_PULL_FAILED",
            message="Failed to pull images from device.",
            details=str(e)
        )


# ---------------------------------------------------------
# MAIN WORKFLOW
# ---------------------------------------------------------

def main():
    try:
        connect_device()
        open_camera()
        trigger_ai_capture()

        today = datetime.date.today().isoformat()
        ts = get_latest_timestamp(today)

        local_folder = pull_images(today, ts)
        return str(local_folder)

    except CaptureError as err:
        # 🎯 Return structured error object to agent.py
        return {
            "error": True,
            "error_code": err.code,
            "message": err.message,
            "details": err.details
        }

    except Exception as e:
        return {
            "error": True,
            "error_code": "UNKNOWN_ERROR",
            "message": "Unexpected error occurred.",
            "details": str(e)
        }


if __name__ == "__main__":
    print(main())
