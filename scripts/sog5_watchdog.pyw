"""
SOG5 Dashboard Watchdog
Dashboard crash yapinca otomatik yeniden baslatir.
pythonw.exe ile calistiginda konsol penceresi gizlenir.
"""
import subprocess
import time
import os
import sys

PYTHON = os.path.join(os.path.dirname(sys.executable), 'pythonw.exe')
DASHBOARD = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'sog5_dashboard.py')
WORKDIR = os.path.dirname(DASHBOARD)
RESTART_DELAY = 10  # seconds

def start_dashboard():
    return subprocess.Popen(
        [PYTHON, DASHBOARD],
        cwd=WORKDIR,
        creationflags=subprocess.CREATE_NO_WINDOW if hasattr(subprocess, 'CREATE_NO_WINDOW') else 0
    )

def main():
    proc = None
    while True:
        if proc is None or proc.poll() is not None:
            if proc is not None:
                time.sleep(RESTART_DELAY)
            proc = start_dashboard()
        time.sleep(5)

if __name__ == '__main__':
    main()
