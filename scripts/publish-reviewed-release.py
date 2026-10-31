"""Upload a reviewed production-only archive using pinned SSH identity.

Credentials are accepted only through HOSTING_SSH_PASSWORD. The archive must
already contain production dependencies and compiled assets, not QA data.
"""
import argparse
import base64
import hashlib
import os
from pathlib import Path
import posixpath
import shlex
import time
import zipfile
import paramiko

parser = argparse.ArgumentParser()
parser.add_argument('archive', type=Path)
args = parser.parse_args()
with zipfile.ZipFile(args.archive) as archive:
    names = archive.namelist()
    for name in names:
        if name.startswith('/') or '..' in name.split('/'):
            raise RuntimeError('Unsafe archive entry')
        if name.startswith(('storage/', 'tests/', 'node_modules/')) or name.endswith(('.sqlite', '.sql')) or '/.env' in '/'+name or name.startswith('bootstrap/cache/') and name.endswith('.php') or name.lower().endswith('qaseeder.php'):
            raise RuntimeError('Non-production content: '+name)
    if 'vendor/phpunit/phpunit/phpunit' in names:
        raise RuntimeError('Development dependencies in release')

transport = paramiko.Transport(('212.1.208.248', 65002))
transport.start_client(timeout=20)
fingerprint = base64.b64encode(hashlib.sha256(transport.get_remote_server_key().asbytes()).digest()).decode().rstrip('=')
if fingerprint != 'GCUZyusIEmRThqBlgYsOGBsNgikg+rM5jvj3+1OgCw4':
    raise RuntimeError('Unexpected SSH host key')
transport.auth_password('u357586881', os.environ['HOSTING_SSH_PASSWORD'])
sftp = paramiko.SFTPClient.from_transport(transport)
stage = '/home/u357586881/lemus-releases/release-'+time.strftime('%Y%m%d%H%M%S', time.gmtime())
def run(command):
    channel = transport.open_session()
    channel.exec_command(command)
    for line in channel.makefile():
        print((line.decode() if isinstance(line, bytes) else line).rstrip(), flush=True)
    errors = channel.makefile_stderr().read().decode()
    if errors:
        print(errors, flush=True)
    if channel.recv_exit_status() != 0:
        raise RuntimeError('Remote command failed; inspect retained stage: '+stage)
try:
    run('mkdir -p '+shlex.quote(stage)+'/source && chmod 700 '+shlex.quote(stage))
    sftp.put(str(args.archive), stage+'/release.zip')
    for local,remote in [('scripts/production-backup-metadata.php','metadata.php'),('scripts/deploy-reviewed-release.sh','deploy.sh')]:
        with sftp.open(stage+'/'+remote,'wb') as target:
            target.write(Path(local).read_bytes().replace(b'\r\n',b'\n'))
    run('unzip -q '+shlex.quote(stage+'/release.zip')+' -d '+shlex.quote(stage+'/source'))
    run('bash '+shlex.quote(stage+'/deploy.sh')+' '+shlex.quote(stage))
finally:
    sftp.close()
    transport.close()
