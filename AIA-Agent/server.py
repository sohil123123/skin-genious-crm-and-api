from flask import Flask, jsonify, request
import subprocess

app = Flask(__name__)

# 🔧 CHANGE THESE PATHS FOR YOUR SYSTEM
python_path = r"/usr/bin/python3"       # Full path to python.exe
script_path = r"/Users/denisemendes/python_script/auto_capture.py"         # Full path to your aia_capture.py script

@app.route("/run", methods=["POST"])
def run_capture():
    try:
        # Run your existing script
        result = subprocess.run(
            [python_path, script_path],
            capture_output=True,
            text=True
        )

        if result.returncode != 0:
            return jsonify({
                "status": "error",
                "output": result.stderr
            }), 500

        return jsonify({
            "status": "success",
            "output": result.stdout
        })

    except Exception as e:
        return jsonify({
            "status": "error",
            "output": str(e)
        }), 500


if __name__ == "__main__":
    # 0.0.0.0 means "listen on all network interfaces"
    app.run(host="0.0.0.0", port=5000)
