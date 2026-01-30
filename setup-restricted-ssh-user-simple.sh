#!/bin/bash

# Simple script to create a restricted SSH user with folder-only access
# Usage: sudo ./setup-restricted-ssh-user-simple.sh <username> [folder_path]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Error: This script must be run as root (use sudo)${NC}"
    exit 1
fi

# Get username from argument
if [ -z "$1" ]; then
    echo -e "${RED}Error: Username is required${NC}"
    echo "Usage: sudo ./setup-restricted-ssh-user-simple.sh <username> [folder_path]"
    exit 1
fi

USERNAME="$1"
FOLDER_PATH="${2:-/var/www/www-root/data/www/anton.panfilius.ru}"

# Validate folder path exists
if [ ! -d "$FOLDER_PATH" ]; then
    echo -e "${RED}Error: Folder path does not exist: $FOLDER_PATH${NC}"
    exit 1
fi

echo -e "${GREEN}Setting up restricted SSH user: $USERNAME${NC}"
echo -e "${YELLOW}Restricted folder: $FOLDER_PATH${NC}"

# Create user if it doesn't exist
if id "$USERNAME" &>/dev/null; then
    echo -e "${YELLOW}User $USERNAME already exists.${NC}"
    read -p "Do you want to reconfigure this user? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 1
    fi
else
    useradd -m -s /bin/bash "$USERNAME"
    echo -e "${GREEN}User $USERNAME created${NC}"
fi

# Create home directory structure
USER_HOME="/home/$USERNAME"
mkdir -p "$USER_HOME/.ssh"
chmod 700 "$USER_HOME/.ssh"

# Create restricted shell script
cat > "$USER_HOME/restricted_shell.sh" << 'EOF'
#!/bin/bash
# Restricted shell that only allows access to a specific folder

RESTRICTED_DIR="/var/www/www-root/data/www/anton.panfilius.ru"

# If SSH_ORIGINAL_COMMAND is set, handle it
if [ -n "$SSH_ORIGINAL_COMMAND" ]; then
    # Parse the command
    CMD="$SSH_ORIGINAL_COMMAND"
    
    # Allow scp and sftp
    if [[ "$CMD" =~ ^(scp|sftp) ]]; then
        eval "$CMD"
        exit $?
    fi
    
    # For other commands, restrict to the folder
    cd "$RESTRICTED_DIR" || {
        echo "Error: Cannot access restricted directory"
        exit 1
    }
    
    # Execute command in restricted directory
    eval "$CMD"
    exit $?
fi

# Interactive shell - change to restricted directory
cd "$RESTRICTED_DIR" || {
    echo "Error: Cannot access restricted directory"
    exit 1
}

# Set restricted environment
export HOME="$RESTRICTED_DIR"
export PATH="/usr/local/bin:/usr/bin:/bin"

# Start restricted bash
exec /bin/bash --restricted -i
EOF

chmod +x "$USER_HOME/restricted_shell.sh"

# Update the restricted directory path in the script
sed -i "s|RESTRICTED_DIR=\"/var/www/www-root/data/www/anton.panfilius.ru\"|RESTRICTED_DIR=\"$FOLDER_PATH\"|g" "$USER_HOME/restricted_shell.sh"

# Set user's shell to restricted shell
usermod -s "$USER_HOME/restricted_shell.sh" "$USERNAME"

# Create SSH authorized_keys if it doesn't exist
if [ ! -f "$USER_HOME/.ssh/authorized_keys" ]; then
    touch "$USER_HOME/.ssh/authorized_keys"
    chmod 600 "$USER_HOME/.ssh/authorized_keys"
fi

# Set ownership
chown -R "$USERNAME:$USERNAME" "$USER_HOME"

# Configure SSH daemon (add to /etc/ssh/sshd_config)
SSH_CONFIG="/etc/ssh/sshd_config"
SSH_CONFIG_BACKUP="/etc/ssh/sshd_config.backup.$(date +%Y%m%d_%H%M%S)"

# Backup SSH config
if [ -f "$SSH_CONFIG" ]; then
    cp "$SSH_CONFIG" "$SSH_CONFIG_BACKUP"
    echo -e "${GREEN}SSH config backed up to: $SSH_CONFIG_BACKUP${NC}"
fi

# Check if configuration already exists
if ! grep -q "Match User $USERNAME" "$SSH_CONFIG" 2>/dev/null; then
    cat >> "$SSH_CONFIG" << SSH_CONFIG_EOF

# Restricted access for user $USERNAME
Match User $USERNAME
    ForceCommand cd $FOLDER_PATH && exec \$SHELL
    AllowTcpForwarding no
    X11Forwarding no
    PermitTunnel no
    # Allow SCP and SFTP
    Subsystem sftp internal-sftp
SSH_CONFIG_EOF
    echo -e "${GREEN}SSH configuration added for user $USERNAME${NC}"
    echo -e "${YELLOW}Note: You will need to restart SSH service: sudo systemctl restart sshd${NC}"
else
    echo -e "${YELLOW}SSH configuration for $USERNAME already exists${NC}"
fi

# Summary
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "User: $USERNAME"
echo "Restricted folder: $FOLDER_PATH"
echo "Home directory: $USER_HOME"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Add SSH public key to: $USER_HOME/.ssh/authorized_keys"
echo "   Example: echo 'your-public-key' | sudo tee -a $USER_HOME/.ssh/authorized_keys"
echo ""
echo "2. (Optional) Set password for user:"
echo "   sudo passwd $USERNAME"
echo ""
echo "3. Restart SSH service:"
echo "   sudo systemctl restart sshd"
echo ""
echo "4. Test connection:"
echo "   ssh $USERNAME@your-server"
echo ""
echo -e "${YELLOW}Security Notes:${NC}"
echo "- The user can only access files within: $FOLDER_PATH"
echo "- TCP forwarding, X11 forwarding, and tunneling are disabled"
echo "- SCP and SFTP are allowed for file transfers"


