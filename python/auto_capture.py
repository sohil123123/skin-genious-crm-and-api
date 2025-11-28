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

CAPTURE_WAIT_SECONDS = 14   # Adjust slightly if needed


# ---------------------------------------------------------
# HELPERS
# ---------------------------------------------------------

def run(cmd):
    print("CMD:", cmd)
    return subprocess.check_output(cmd, shell=True, text=True)

def adb(cmd):
    return run(f"adb -s {DEVICE} {cmd}")

def connect_device():
    print("🔌 Connecting to device…")
    try:
        run(f"adb connect {DEVICE}")
    except:
        pass
    time.sleep(1)
    print("✔ Connected.")


def open_camera():
    print("📸 Launching CameraActivity…")
    adb(f"shell am start -n {CAMERA_ACTIVITY}")
    time.sleep(2)


def trigger_ai_capture():
    print("✨ Triggering AI Skin Detect button…")
    adb(f"shell input tap {AI_TAP_X} {AI_TAP_Y}")
    print("⏳ Waiting for 6-light capture cycle…")
    time.sleep(CAPTURE_WAIT_SECONDS)


def get_latest_timestamp(today):
    print("🔍 Searching latest timestamp folder…")
    result = adb(f"shell ls -t {REMOTE_ROOT}/{today}")

    files = result.strip().split("\n")

    if not files:
        raise Exception("❌ No timestamp folder found on device")

    # Extract timestamp prefix from first file
    first_file = files[0]
    timestamp = first_file.split("-")[0]  # e.g. 142548-image.jpg → 142548

    print("📌 Latest timestamp:", timestamp)
    return timestamp


def pull_images(today, ts):
    local_dir = LOCAL_ROOT / today / ts
    local_dir.mkdir(parents=True, exist_ok=True)

    print("⬇ Pulling images to:", local_dir)

    adb(f"pull {REMOTE_ROOT}/{today}/{ts}-image.jpg '{local_dir}'")
    adb(f"pull {REMOTE_ROOT}/{today}/{ts}-image_positive.jpg '{local_dir}'")
    adb(f"pull {REMOTE_ROOT}/{today}/{ts}-image_negative.jpg '{local_dir}'")
    adb(f"pull {REMOTE_ROOT}/{today}/{ts}-image_uv.jpg '{local_dir}'")
    adb(f"pull {REMOTE_ROOT}/{today}/{ts}-image_woods.jpg '{local_dir}'")
    adb(f"pull {REMOTE_ROOT}/{today}/{ts}-image_blue.jpg '{local_dir}'")

    print("✔ Images downloaded.")
    return local_dir


# ---------------------------------------------------------
# MAIN WORKFLOW
# ---------------------------------------------------------

def main():
    print("\n🚀 AIA AUTO-CAPTURE STARTED\n")

    connect_device()
    # open_camera()
    # trigger_ai_capture()

    # today = datetime.date.today().isoformat()
    # ts = get_latest_timestamp(today)

    # local = pull_images(today, ts)

    # print("\n🎉 DONE!")
    # print("📁 Saved at:", local)


if __name__ == "__main__":
    main()
