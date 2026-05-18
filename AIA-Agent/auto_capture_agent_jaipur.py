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
from io import StringIO
from contextlib import redirect_stdout, redirect_stderr
import subprocess as sp
import datetime
import pathlib

# ---------------------------------------------------------
# CONFIGURATION
# ---------------------------------------------------------

API_KEY = "2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE"
HOST = "0.0.0.0"
PORT = 5000
LOG_FILE = "auto_capture_agent_jaipur.log"

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


DEVICE = "192.168.1.10:5555"
PACKAGE = "com.silverllt.skincenter.rkss.a5.production"
CAMERA_ACTIVITY = "com.silverllt.skincenter.rkss.a5.production/com.silverllt.skincenter.ui.CameraActivity"

REMOTE_ROOT = "/sdcard/Android/data/com.silverllt.skincenter.rkss.a5.production/files/skin/706210"

AI_TAP_X = 540
AI_TAP_Y = 1700
CAMERA_TAB_X = 540
CAMERA_TAB_Y = 1770
PROFILE_TAP_X = 770
PROFILE_TAP_Y = 350
PROFILE_CAMERA_X = 980
PROFILE_CAMERA_Y = 208
CAPTURE_WAIT_SECONDS = 25


def run(cmd):
    try:
        return sp.check_output(cmd, shell=True, text=True)
    except sp.CalledProcessError as e:
        raise CaptureError("ADB_COMMAND_FAILED", f"ADB failed: {cmd}", str(e))


def adb(cmd, device_ip):
    return run(f"adb -s {device_ip} {cmd}")


def connect_device(device_ip):
    out = run(f"adb connect {device_ip}")
    if "connected" not in out.lower():
        raise CaptureError(
            "DEVICE_CONNECTION_FAILED",
            "Could not connect to scanner.",
            out
        )
    time.sleep(1)


def open_camera(device_ip):
    adb(f"shell am start -n {CAMERA_ACTIVITY}", device_ip)
    time.sleep(2)


def open_camera_by_tap(device_ip):
    adb(f"shell input tap {PROFILE_CAMERA_X} {PROFILE_CAMERA_Y}", device_ip)
    time.sleep(3)


def trigger_ai_capture(device_ip):
    adb(f"shell input tap {AI_TAP_X} {AI_TAP_Y}", device_ip)
    time.sleep(CAPTURE_WAIT_SECONDS)


def enter_existing_profile(device_ip):
    # Tap the existing profile row on Member Center screen
    adb(f"shell input tap {PROFILE_TAP_X} {PROFILE_TAP_Y}", device_ip)
    time.sleep(3)


def get_latest_task_folder(device_ip):
    result = adb(f"shell ls -t {REMOTE_ROOT}", device_ip)
    folders = [x.strip() for x in result.splitlines() if x.strip().isdigit()]

    if not folders:
        raise CaptureError("NO_TASK_FOLDER_FOUND", "No task folder found", result)

    return folders[0]


def pull_images(task_id, device_ip):
    today = datetime.date.today().isoformat()
    local_dir = LOCAL_ROOT / today / task_id
    local_dir.mkdir(parents=True, exist_ok=True)

    mapping = {
        "12.jpg": "surface_polarized.jpg",
        "22.jpg": "subsurface_polarized.jpg",
        "32.jpg": "white.jpg",
        "42.jpg": "woods_uv.jpg",
        "52.jpg": "red.jpg"
    }

    for orig, new in mapping.items():
        remote_path = f"{REMOTE_ROOT}/{task_id}/{orig}"

        pulled = False
        for attempt in range(10):
            try:
                adb(f"pull {remote_path} '{local_dir}'", device_ip)
                pulled = True
                break
            except CaptureError:
                time.sleep(2)

        if not pulled:
            raise CaptureError("IMAGE_MISSING", f"{orig} missing after retries", remote_path)

        orig_file = local_dir / orig
        new_file = local_dir / new

        if orig_file.exists():
            orig_file.rename(new_file)
        else:
            raise CaptureError("IMAGE_MISSING", f"{orig} missing", remote_path)

    return local_dir


def auto_capture_main(device_ip):
    try:
        connect_device(device_ip)
        enter_existing_profile(device_ip)
        open_camera_by_tap(device_ip)
        trigger_ai_capture(device_ip)

        task_id = get_latest_task_folder(device_ip)
        return str(pull_images(task_id, device_ip))

    except CaptureError as err:
        return {"error": True, **err.__dict__}

    except Exception as e:
        return {"error": True, "error_code": "UNKNOWN", "message": str(e)}


def pull_last_images_only(device_ip):
    """
    Pull the last saved images from the Bitmojis machine
    WITHOUT triggering a new capture session.
    Connects to the device, finds the latest task folder,
    and pulls those images to the local directory.
    If images already exist locally (from a prior capture), skip re-pulling.
    """
    try:
        connect_device(device_ip)

        task_id = get_latest_task_folder(device_ip)
        logger.info(f"Found latest task folder: {task_id}")

        # Check if images already exist locally
        today = datetime.date.today().isoformat()
        local_dir = LOCAL_ROOT / today / task_id

        expected_files = [
            "surface_polarized.jpg",
            "subsurface_polarized.jpg",
            "white.jpg",
            "woods_uv.jpg",
            "red.jpg"
        ]

        all_exist = local_dir.exists() and all(
            (local_dir / f).exists() for f in expected_files
        )

        if all_exist:
            folder_path = str(local_dir)
            logger.info(f"Images already exist locally at: {folder_path}")
        else:
            folder_path = str(pull_images(task_id, device_ip))
            logger.info(f"Images pulled to: {folder_path}")

        return {"error": False, "task_id": task_id, "folder": folder_path}

    except CaptureError as err:
        return {"error": True, "code": err.code, "message": err.message, "details": err.details}

    except Exception as e:
        return {"error": True, "code": "UNKNOWN", "message": str(e)}


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


def run_auto_capture(device_ip):
    logger.info(f"Running auto_capture for device: {device_ip}")

    stdout_buffer = StringIO()
    stderr_buffer = StringIO()

    with redirect_stdout(stdout_buffer), redirect_stderr(stderr_buffer):
        result = auto_capture_main(device_ip)

    if isinstance(result, dict) and result.get("error"):
        return False, result, None

    folder_path = result

    # Cross-platform folder open
    open_folder_cross_platform(folder_path)

    return True, stdout_buffer.getvalue(), folder_path


@app.route("/auto-capture-process", methods=["GET", "POST"])
def auto_capture_process():
    # Get device_ip from request, fallback to global DEVICE
    device_ip = request.args.get('device_ip') or request.form.get('device_ip') or DEVICE

    ok, out, folder = run_auto_capture(device_ip)
    if ok:
        return jsonify({"status": "success", "folder": folder, "output": out})
    return jsonify({"status": "error", **out}), 500


@app.route("/pull-last-images", methods=["GET", "POST"])
def pull_last_images_route():
    """
    Pull the last saved images from the Bitmojis machine
    without triggering a new capture.
    """
    device_ip = request.args.get('device_ip') or request.form.get('device_ip') or DEVICE

    logger.info(f"Pull last images requested for device: {device_ip}")

    result = pull_last_images_only(device_ip)

    if result.get("error"):
        return jsonify({"status": "error", **result}), 500

    # Open the folder on the local machine
    open_folder_cross_platform(result["folder"])

    return jsonify({
        "status": "success",
        "task_id": result["task_id"],
        "folder": result["folder"]
    })


if __name__ == "__main__":
    logger.info(f"Starting agent on {HOST}:{PORT}")
    app.run(host=HOST, port=PORT)
