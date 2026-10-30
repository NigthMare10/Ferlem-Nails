"""Read-only source snapshot. Credentials are supplied through the environment."""
import hashlib
import json
import os
from pathlib import Path
import stat
import paramiko

destination = Path(os.environ['SNAPSHOT_DIRECTORY'])
root = '/home/u357586881/domains/steelblue-opossum-594306.hostingersite.com/studio-lemus'
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.RejectPolicy())
transport = paramiko.Transport(('212.1.208.248', 65002))
transport.start_client(timeout=20)
import base64
fingerprint = base64.b64encode(hashlib.sha256(transport.get_remote_server_key().asbytes()).digest()).decode().rstrip('=')
if fingerprint != 'GCUZyusIEmRThqBlgYsOGBsNgikg+rM5jvj3+1OgCw4':
    raise RuntimeError('Unexpected SSH host key')
transport.auth_password('u357586881', os.environ['HOSTING_SSH_PASSWORD'])
sftp = paramiko.SFTPClient.from_transport(transport)
changes = []

def download(relative):
    for entry in sftp.listdir_attr(root + '/' + relative):
        path = relative + '/' + entry.filename
        if entry.filename in ('cache', '.env', '.git', 'node_modules', 'vendor'):
            continue
        if stat.S_ISDIR(entry.st_mode):
            download(path)
        elif stat.S_ISREG(entry.st_mode) and entry.filename.endswith(('.php', '.vue', '.ts', '.js', '.css', '.json')):
            data = sftp.open(root + '/' + path, 'rb').read()
            target = destination / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
            local = Path(path)
            if not local.exists() or local.read_bytes().replace(b'\r\n', b'\n') != data.replace(b'\r\n', b'\n'):
                changes.append({'path': path, 'status': 'modified' if local.exists() else 'new'})

try:
    for directory in ('app', 'resources', 'routes', 'config', 'database', 'tests'):
        try:
            download(directory)
        except FileNotFoundError:
            pass
    destination.mkdir(parents=True, exist_ok=True)
    (destination / 'changes.json').write_text(json.dumps(changes, indent=2))
    print(json.dumps(changes, indent=2))
finally:
    sftp.close()
    transport.close()
