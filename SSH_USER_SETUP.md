# SSH Restricted User Setup Guide

This guide explains how to create a restricted SSH user that can only access this specific folder.

## Quick Start

### Option 1: Simple Setup (Recommended)

```bash
sudo ./setup-restricted-ssh-user-simple.sh <username>
```

Example:
```bash
sudo ./setup-restricted-ssh-user-simple.sh deployer
```

### Option 2: Advanced Setup (with chroot)

```bash
sudo ./setup-restricted-ssh-user.sh <username>
```

## What These Scripts Do

1. **Create a new Linux user** (if it doesn't exist)
2. **Set up a restricted shell** that only allows access to this folder
3. **Configure SSH** to enforce restrictions
4. **Disable dangerous features** like port forwarding and X11 forwarding

## After Running the Script

### 1. Add SSH Public Key

Add the user's public SSH key to allow key-based authentication:

```bash
sudo nano /home/<username>/.ssh/authorized_keys
# Or use:
echo "ssh-rsa AAAAB3NzaC1yc2E..." | sudo tee -a /home/<username>/.ssh/authorized_keys
```

### 2. (Optional) Set Password

If you want password authentication:

```bash
sudo passwd <username>
```

### 3. Restart SSH Service

```bash
sudo systemctl restart sshd
# Or on some systems:
sudo service ssh restart
```

### 4. Test Connection

```bash
ssh <username>@your-server-ip
```

The user should be automatically placed in the restricted folder and unable to navigate outside of it.

## Security Features

- ✅ User can only access files within the specified folder
- ✅ TCP forwarding disabled
- ✅ X11 forwarding disabled
- ✅ Tunneling disabled
- ✅ SCP and SFTP allowed for file transfers

## Troubleshooting

### User can't connect
- Check SSH service is running: `sudo systemctl status sshd`
- Check SSH logs: `sudo tail -f /var/log/auth.log` (or `/var/log/secure` on CentOS/RHEL)
- Verify SSH config: `sudo sshd -t`

### User can access other folders
- Verify the restricted shell is set: `grep <username> /etc/passwd`
- Check SSH config: `sudo grep -A 5 "Match User <username>" /etc/ssh/sshd_config`
- Ensure SSH service was restarted after configuration

### Permission denied errors
- Check folder permissions: `ls -la /var/www/www-root/data/www/anton.panfilius.ru`
- Ensure user has read/write access to the folder
- Check ownership: `sudo chown -R <username>:<username> /var/www/www-root/data/www/anton.panfilius.ru` (if needed)

## Removing a Restricted User

To remove a restricted user:

```bash
# Remove SSH configuration
sudo sed -i '/Match User <username>/,/^$/d' /etc/ssh/sshd_config
sudo systemctl restart sshd

# Remove the user (optional)
sudo userdel -r <username>
```

## Notes

- The scripts create backups of your SSH configuration before making changes
- The restricted folder path is: `/var/www/www-root/data/www/anton.panfilius.ru`
- You can specify a different folder as the second argument to the script


