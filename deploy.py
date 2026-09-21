import ftplib
import os
import sys

sys.stdout.reconfigure(encoding='utf-8')

def load_env():
    env_file = os.path.join(LOCAL_ROOT, ".env")
    if os.path.exists(env_file):
        with open(env_file, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    os.environ.setdefault(k.strip(), v.strip())

LOCAL_ROOT = os.path.dirname(os.path.abspath(__file__))
load_env()

FTP_HOST = os.environ.get("FTP_HOST", "s12.thehost.com.ua")
FTP_PORT = int(os.environ.get("FTP_PORT", 21))
FTP_USER = os.environ.get("FTP_USER", "sc")
FTP_PASS = os.environ.get("FTP_PASS", "")

def ensure_remote_dir(ftp, remote_dir):
    parts = remote_dir.strip('/').split('/')
    current = ""
    for part in parts:
        if not part:
            continue
        current += ("/" if current else "") + part
        try:
            ftp.mkd(current)
        except Exception:
            pass

def upload_file(ftp, local_path, remote_path):
    parent = os.path.dirname(remote_path).replace("\\", "/")
    if parent:
        ensure_remote_dir(ftp, parent)
    
    with open(local_path, "rb") as f:
        ftp.storbinary(f"STOR {remote_path.replace(chr(92), '/')}", f)
    print(f"  ✓ Uploaded: {remote_path}")

def sync_dir(ftp, local_dir, remote_prefix=""):
    if not os.path.exists(local_dir):
        return
    for root, dirs, files in os.walk(local_dir):
        rel_dir = os.path.relpath(root, local_dir)
        if rel_dir == ".":
            remote_dir = remote_prefix
        else:
            remote_dir = os.path.join(remote_prefix, rel_dir).replace("\\", "/")
        
        if remote_dir:
            ensure_remote_dir(ftp, remote_dir)
        
        for f in files:
            lp = os.path.join(root, f)
            rp = os.path.join(remote_dir, f).replace("\\", "/") if remote_dir else f
            upload_file(ftp, lp, rp)

def deploy():
    print(f"Connecting to FTP {FTP_HOST}...")
    ftp = ftplib.FTP()
    ftp.connect(FTP_HOST, FTP_PORT, timeout=30)
    ftp.login(FTP_USER, FTP_PASS)
    print("Connected successfully!\n")

    # 1. Root files
    root_files = ["index.php", "admin.php", "cron.php", "api.php", "sitemap.php", "robots.txt", ".htaccess", ".env"]
    for rf in root_files:
        lp = os.path.join(LOCAL_ROOT, rf)
        if os.path.exists(lp):
            upload_file(ftp, lp, rf)

    # 2. Key Directories
    sync_dir(ftp, os.path.join(LOCAL_ROOT, "css"), "css")
    sync_dir(ftp, os.path.join(LOCAL_ROOT, "js"), "js")
    sync_dir(ftp, os.path.join(LOCAL_ROOT, "views"), "views")
    sync_dir(ftp, os.path.join(LOCAL_ROOT, "app"), "app")

    # 3. Data JSON configs
    data_dir = os.path.join(LOCAL_ROOT, "data")
    if os.path.exists(data_dir):
        for f in os.listdir(data_dir):
            if f.endswith(".json"):
                upload_file(ftp, os.path.join(data_dir, f), f"data/{f}")

    ftp.quit()
    print("\n✓ Deployment completed successfully!")

if __name__ == '__main__':
    deploy()
