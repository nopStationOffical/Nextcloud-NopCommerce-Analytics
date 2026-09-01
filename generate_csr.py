import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('10.112.165.132', username='noptraining', password='nopTraining@2026#', timeout=30)

cmd = "echo nopTraining@2026# | sudo -S docker exec -u www-data nextcloud_server-app-1 sh -c 'mkdir -p /tmp/certs && openssl req -nodes -newkey rsa:4096 -keyout /tmp/certs/nopstation_analytics.key -out /tmp/certs/nopstation_analytics.csr -subj \"/CN=nopstation_analytics\"'"
stdin, stdout, stderr = ssh.exec_command(cmd, get_pty=True)
out = stdout.read().decode('utf-8', errors='replace')
print('OPENSSL OUT:', out)

cmd2 = "echo nopTraining@2026# | sudo -S docker cp nextcloud_server-app-1:/tmp/certs /tmp/certs"
ssh.exec_command(cmd2, get_pty=True)[1].read()

sftp = ssh.open_sftp()
try:
    sftp.get('/tmp/certs/nopstation_analytics.key', 'c:/001.Data/BrainStation/NextCloud/nopstation_analytics/build/sign/nopstation_analytics.key')
    sftp.get('/tmp/certs/nopstation_analytics.csr', 'c:/001.Data/BrainStation/NextCloud/nopstation_analytics/build/sign/nopstation_analytics.csr')
    print('Files downloaded successfully.')
except Exception as e:
    print('SFTP Error:', e)
sftp.close()

ssh.close()
