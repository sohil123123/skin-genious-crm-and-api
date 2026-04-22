from flask import Flask, request, jsonify
import logging
import os
import time
import platform
import subprocess as sp
import datetime
import pathlib
from io import StringIO
from contextlib import redirect_stdout, redirect_stderr

# ---------------------------------------------------------
# CONFIG
# ---------------------------------------------------------

API_KEY = "2Yx6pqydyFpmf8K1RU4N1oOgYyAhdCJE"
HOST = "0.0.0.0"
PORT = 8000
LOG_FILE = "auto_capture_agent.log"

ADB_PATH = "/opt/homebrew/bin/adb"
DEVICE_IP = "192.168.31.177"

REMOTE_ROOT = "/sdcard/yiyuan/image"
CAMERA_ACTIVITY = "com.yiyuan.skin/.ui.activity.CameraActivity"

AI_TAP_X = 539
AI_TAP_Y = 1789
CAPTURE_WAIT_SECONDS = 14

# ---------------------------------------------------------
# LOGGING
# ---------------------------------------------------------

logger = logging.getLogger("agent")
logger.setLevel(logging.INFO)

formatter = logging.Formatter("[%(asctime)s] %(message)s")

ch = logging.StreamHandler()
ch.setFormatter(formatter)
logger.addHandler(ch)

fh = logging.FileHandler(LOG_FILE)
fh.setFormatter(formatter)
logger.addHandler(fh)

# ---------------------------------------------------------
# USER PATH
# ---------------------------------------------------------

def get_actual_user_home():
    try:
        user = sp.check_output("stat -f%Su /dev/console", shell=True, text=True).strip()
        return pathlib.Path(f"/Users/{user}")
    except:
        return pathlib.Path.home()

USER_HOME = get_actual_user_home()
LOCAL_ROOT = USER_HOME / "Desktop" / "revised AIA database"

# ---------------------------------------------------------
# UTIL
# ---------------------------------------------------------

class CaptureError(Exception):
    pass


def run(cmd):
    try:
        return sp.check_output(cmd, shell=True, text=True)
    except sp.CalledProcessError as e:
        raise CaptureError(str(e))


# ---------------------------------------------------------
# ADB STABLE LOGIC
# ---------------------------------------------------------

def reset_adb():
    os.system("killall adb > /dev/null 2>&1")
    time.sleep(1)
    os.system(f"{ADB_PATH} start-server > /dev/null 2>&1")


def is_device_reachable():
    return os.system(f"ping -c 1 {DEVICE_IP} > /dev/null 2>&1") == 0


def get_connected_devices():
    out = os.popen(f"{ADB_PATH} devices").read()
    lines = out.strip().split("\n")[1:]
    return [line.split()[0] for line in lines if "device" in line]


def try_connect():
    ports = [5555, 5556, 38467, 58526]

    for port in ports:
        out = os.popen(f"{ADB_PATH} connect {DEVICE_IP}:{port}").read()
        if "connected" in out.lower() or "already connected" in out.lower():
            return f"{DEVICE_IP}:{port}"

    return None


def ensure_device():
    if not is_device_reachable():
        raise CaptureError("Device not reachable (network issue)")

    reset_adb()

    devices = get_connected_devices()
    for d in devices:
        if DEVICE_IP in d:
            return d

    device = try_connect()
    if device:
        return device

    raise CaptureError("ADB connection failed")


def adb(cmd):
    device = ensure_device()
    return run(f"{ADB_PATH} -s {device} {cmd}")


def safe_adb(cmd, retries=3):
    for i in range(retries):
        try:
            return adb(cmd)
        except Exception as e:
            time.sleep(2)
    raise CaptureError("ADB failed after retries")

# ---------------------------------------------------------
# CORE LOGIC
# ---------------------------------------------------------

def open_camera():
    safe_adb(f"shell am start -n {CAMERA_ACTIVITY}")
    time.sleep(2)


def trigger_capture():
    safe_adb(f"shell input tap {AI_TAP_X} {AI_TAP_Y}")
    time.sleep(CAPTURE_WAIT_SECONDS)


def get_latest_timestamp(today):
    result = safe_adb(f"shell ls -t {REMOTE_ROOT}/{today}")
    files = result.strip().split("\n")
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
        safe_adb(f"pull {REMOTE_ROOT}/{today}/{orig} '{local_dir}'")

        orig_file = local_dir / orig
        new_file = local_dir / new

        if orig_file.exists():
            orig_file.rename(new_file)
        else:
            raise CaptureError(f"{orig} missing")

    return str(local_dir)


def auto_capture():
    today = datetime.date.today().isoformat()

    open_camera()
    trigger_capture()

    ts = get_latest_timestamp(today)
    return pull_images(today, ts)

# ---------------------------------------------------------
# FLASK
# ---------------------------------------------------------

app = Flask(__name__)

def check_api():
    key = request.headers.get("X-API-KEY")
    return key == API_KEY


@app.before_request
def auth():
    if request.path != "/health" and not check_api():
        return jsonify({"error": "Unauthorized"}), 401


@app.route("/health")
def health():
    return jsonify({"status": "ok"})


@app.route("/auto-capture-process", methods=["GET"])
def run_process():
    try:
        out = auto_capture()
        return jsonify({"status": "success", "folder": out})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500


# ---------------------------------------------------------
# RUN
# ---------------------------------------------------------

if __name__ == "__main__":
    logger.info(f"Starting on {HOST}:{PORT}")
    app.run(host=HOST, port=PORT)
