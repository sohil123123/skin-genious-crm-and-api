import subprocess
import json
import sys

DEVICE = "192.168.31.177:5555"

def run(cmd):
    try:
        result = subprocess.check_output(cmd, shell=True, stderr=subprocess.STDOUT)
        return result.decode().strip()
    except subprocess.CalledProcessError as e:
        return e.output.decode().strip()

def connect_device():
    cmd = f"adb connect {DEVICE}"
    output = run(cmd)

    if "connected" in output.lower():
        return {"success": True, "message": "Connected", "output": output}

    return {"success": False, "message": "Failed to connect", "output": output}


if __name__ == "__main__":
    result = connect_device()
    print(json.dumps(result))  # Laravel will read this
