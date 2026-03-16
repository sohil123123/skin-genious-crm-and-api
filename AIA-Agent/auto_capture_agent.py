"""
-------------------------------------------
Combined agent.py + auto_capture.py

Features:
- Dynamic user home detection (works on ANY Mac)
- Saves images to logged-in user's Desktop (not /var/root)
- Opens folder correctly from LaunchDaemon
- Cloudflare-ready Flask server
-------------------------------------------
"""

from flask import Flask, request, jsonify
import logging
import os
import time
import platform
import subprocess
import traceback
from io import StringIO
from contextlib import redirect_stdout, redirect_stderr
import subprocess as sp
import datetime
import pathlib
import pathlib

# ---------------------------------------------------------
# CONFIGURATION
# ---------------------------------------------------------

API_KEY = "2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE"
HOST = "0.0.0.0"
PORT = 5000
LOG_FILE = "auto_capture_agent.log"

# ---------------------------------------------------------
# LOGGING SETUP
# ---------------------------------------------------------

os.makedirs(os.path.dirname(os.path.abspath(LOG_FILE)), exist_ok=True)

logger = logging.getLogger("auto_capture_agent")
logger.setLevel(logging.INFO)

formatter = logging.Formatter(
    "[%(asctime)s] [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S"
)

ch = logging.StreamHandler()
ch.setFormatter(formatter)
logger.addHandler(ch)

fh = logging.FileHandler(LOG_FILE, encoding="utf-8")
fh.setFormatter(formatter)
logger.addHandler(fh)

# ---------------------------------------------------------
# DYNAMIC USER HOME (works on ANY Mac)
# ---------------------------------------------------------

def get_actual_user_home():
    """
    Returns the home directory of the real logged-in macOS user (not root),
    without using pwd module (supports all Python builds).
    """
    try:
        # macOS: obtain current console/GUI user
        user = subprocess.check_output(
            "stat -f%Su /dev/console", shell=True, text=True
        ).strip()

        # Build home directory path manually
        return pathlib.Path(f"/Users/{user}")
    except Exception as e:
        # Fallback to root (not ideal, but safe)
        return pathlib.Path.home()


USER_HOME = get_actual_user_home()

LOCAL_ROOT = USER_HOME / "Desktop" / "revised AIA database"


def open_folder_cross_platform(path):
    try:
        if platform.system() == "Windows":
            # Works even when running in privileged mode
            subprocess.Popen(['powershell', '-Command', f'Start-Process "{path}"'])

        elif platform.system() == "Darwin":
            subprocess.Popen(["open", path])

        else:  # Linux
            subprocess.Popen(["xdg-open", path])

    except Exception as e:
        logger.error(f"Failed to open folder: {e}")

# ---------------------------------------------------------
# AUTO CAPTURE MODULE
# ---------------------------------------------------------

class CaptureError(Exception):
    def __init__(self, code, message, details=None):
        self.code = code
        self.message = message
        self.details = details
        super().__init__(message)


DEVICE = "192.168.31.177:5000"
PACKAGE = "com.yiyuan.skin"
CAMERA_ACTIVITY = "com.yiyuan.skin/.ui.activity.CameraActivity"

REMOTE_ROOT = "/sdcard/yiyuan/image"

AI_TAP_X = 539
AI_TAP_Y = 1789
CAPTURE_WAIT_SECONDS = 14


def run(cmd):
    try:
        return sp.check_output(cmd, shell=True, text=True)
    except sp.CalledProcessError as e:
        raise CaptureError("ADB_COMMAND_FAILED", f"ADB failed: {cmd}", str(e))


def adb(cmd):
    return run(f"adb -s {DEVICE} {cmd}")


def connect_device():
    out = run(f"adb connect {DEVICE}")
    if "connected" not in out.lower():
        raise CaptureError(
            "DEVICE_CONNECTION_FAILED",
            "Could not connect to scanner.",
            out
        )
    time.sleep(1)


def open_camera():
    adb(f"shell am start -n {CAMERA_ACTIVITY}")
    time.sleep(2)


def trigger_ai_capture():
    adb(f"shell input tap {AI_TAP_X} {AI_TAP_Y}")
    time.sleep(CAPTURE_WAIT_SECONDS)


def get_latest_timestamp(today):
    result = adb(f"shell ls -t {REMOTE_ROOT}/{today}")
    files = result.strip().split("\n")

    if not files or files == ['']:
        raise CaptureError("NO_TIMESTAMP_FOUND", "No folder found", result)

    return files[0].split("-")[0]


def pull_images(today, ts):
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

    for orig, new in mapping.items():
        remote_path = f"{REMOTE_ROOT}/{today}/{orig}"
        adb(f"pull {remote_path} '{local_dir}'")

        orig_file = local_dir / orig
        new_file = local_dir / new

        if orig_file.exists():
            orig_file.rename(new_file)
        else:
            raise CaptureError("IMAGE_MISSING", f"{orig} missing", remote_path)

    return local_dir


def auto_capture_main():
    try:
        connect_device()
        open_camera()
        trigger_ai_capture()

        today = datetime.date.today().isoformat()
        ts = get_latest_timestamp(today)

        return str(pull_images(today, ts))

    except CaptureError as err:
        return {"error": True, **err.__dict__}

    except Exception as e:
        return {"error": True, "error_code": "UNKNOWN", "message": str(e)}


# ---------------------------------------------------------
# FLASK SERVER
# ---------------------------------------------------------

app = Flask(__name__)


def check_api_key():
    key = request.headers.get("X-API-KEY")
    return (not API_KEY) or key == API_KEY


@app.before_request
def before_request():
    if request.path != "/health" and not check_api_key():
        return jsonify({"status": "error", "message": "Unauthorized"}), 401


@app.route("/health")
def health():
    return jsonify({"status": "ok", "user_home": str(USER_HOME)})


def run_auto_capture():
    logger.info("Running auto_capture")

    stdout_buffer = StringIO()
    stderr_buffer = StringIO()

    with redirect_stdout(stdout_buffer), redirect_stderr(stderr_buffer):
        result = auto_capture_main()

    if isinstance(result, dict) and result.get("error"):
        return False, result, None

    folder_path = result

    # Cross-platform folder open
    open_folder_cross_platform(folder_path)

    # # Try to open folder for the logged-in user
    # try:
    #     subprocess.Popen(["open", folder_path])
    # except Exception as e:
    #     logger.error(f"Failed to open folder: {e}")

    return True, stdout_buffer.getvalue(), folder_path


@app.route("/auto-capture-process", methods=["GET", "POST"])
def auto_capture_process():
    ok, out, folder = run_auto_capture()
    if ok:
        return jsonify({"status": "success", "folder": folder, "output": out})
    return jsonify({"status": "error", **out}), 500


if __name__ == "__main__":
    logger.info(f"Starting agent on {HOST}:{PORT}")
    app.run(host=HOST, port=PORT)
