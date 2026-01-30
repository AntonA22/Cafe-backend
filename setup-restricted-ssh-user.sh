#!/bin/bash

# Script to create a restricted SSH user that can only access a specific folder
# Usage: sudo ./setup-restricted-ssh-user.sh <username> [folder_path]

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
    echo "Usage: sudo ./setup-restricted-ssh-user.sh <username> [folder_path]"
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

# Create user with no login shell initially
if id "$USERNAME" &>/dev/null; then
    echo -e "${YELLOW}User $USERNAME already exists. Skipping user creation.${NC}"
else
    useradd -m -s /bin/bash "$USERNAME"
    echo -e "${GREEN}User $USERNAME created${NC}"
fi

# Create home directory structure
USER_HOME="/home/$USERNAME"
mkdir -p "$USER_HOME/.ssh"
mkdir -p "$USER_HOME/jail"

# Create chroot jail structure
JAIL_DIR="$USER_HOME/jail"
mkdir -p "$JAIL_DIR"/{bin,lib,lib64,usr/bin,usr/lib,etc,dev,proc,sys,tmp,var/www}

# Copy necessary binaries for chroot (minimal set)
echo -e "${YELLOW}Setting up chroot jail...${NC}"

# Copy bash and basic utilities
for BIN in /bin/bash /bin/ls /bin/cat /bin/pwd /bin/mkdir /bin/rm /usr/bin/scp /usr/bin/sftp-server; do
    if [ -f "$BIN" ]; then
        BIN_DIR=$(dirname "$BIN" | sed 's|^/||')
        mkdir -p "$JAIL_DIR/$BIN_DIR"
        cp "$BIN" "$JAIL_DIR/$BIN_DIR/"
        
        # Copy required libraries
        ldd "$BIN" 2>/dev/null | grep -o '/[^ ]*' | while read lib; do
            if [ -f "$lib" ]; then
                LIB_DIR=$(dirname "$lib" | sed 's|^/||')
                mkdir -p "$JAIL_DIR/$LIB_DIR"
                cp "$lib" "$JAIL_DIR/$LIB_DIR/" 2>/dev/null || true
            fi
        done
    fi
done

# Create symlink to the actual folder
ln -sf "$FOLDER_PATH" "$JAIL_DIR/var/www/$(basename $FOLDER_PATH)"

# Set up /etc in chroot
cp /etc/passwd "$JAIL_DIR/etc/" 2>/dev/null || echo "root:x:0:0:root:/root:/bin/bash" > "$JAIL_DIR/etc/passwd"
cp /etc/group "$JAIL_DIR/etc/" 2>/dev/null || echo "root:x:0:" > "$JAIL_DIR/etc/group"

# Create restricted shell script
cat > "$USER_HOME/restricted_shell.sh" << 'EOF'
#!/bin/bash
# Restricted shell that only allows access to the chrooted folder

# Get the chroot directory
CHROOT_DIR="$HOME/jail"

# Change to the restricted folder
cd "$CHROOT_DIR/var/www" 2>/dev/null || cd "$CHROOT_DIR"

# Start bash in chroot
exec /usr/sbin/chroot "$CHROOT_DIR" /bin/bash
EOF

chmod +x "$USER_HOME/restricted_shell.sh"

# Alternative: Use rssh if available, or create a simpler restricted shell
cat > "$USER_HOME/restricted_shell_simple.sh" << 'RESTRICTED_EOF'
#!/bin/bash
# Simple restricted shell - only allows access to specific folder

RESTRICTED_DIR="/var/www/www-root/data/www/anton.panfilius.ru"

# Only allow commands that work within the restricted directory
case "$SSH_ORIGINAL_COMMAND" in
    "")
        # Interactive shell - change to restricted directory
        cd "$RESTRICTED_DIR" || exit 1
        exec /bin/bash --restricted
        ;;
    scp*)
        # Allow SCP
        eval "$SSH_ORIGINAL_COMMAND"
        ;;
    sftp*)
        # Allow SFTP
        eval "$SSH_ORIGINAL_COMMAND"
        ;;
    "cd "*|"ls "*|"cat "*|"pwd"|"mkdir "*|"rm "*)
        # Allow basic commands within restricted directory
        cd "$RESTRICTED_DIR" || exit 1
        eval "$SSH_ORIGINAL_COMMAND"
        ;;
    *)
        echo "Access denied. Only basic file operations are allowed."
        exit 1
        ;;
esac
RESTRICTED_EOF

chmod +x "$USER_HOME/restricted_shell_simple.sh"

# Set user's shell to restricted shell
usermod -s "$USER_HOME/restricted_shell_simple.sh" "$USERNAME"

# Set ownership
chown -R "$USERNAME:$USERNAME" "$USER_HOME"
chmod 700 "$USER_HOME/.ssh"

# Create SSH authorized_keys template
if [ ! -f "$USER_HOME/.ssh/authorized_keys" ]; then
    touch "$USER_HOME/.ssh/authorized_keys"
    chmod 600 "$USER_HOME/.ssh/authorized_keys"
    chown "$USERNAME:$USERNAME" "$USER_HOME/.ssh/authorized_keys"
fi

# Configure SSH daemon (add to /etc/ssh/sshd_config)
SSH_CONFIG="/etc/ssh/sshd_config"
SSH_CONFIG_BACKUP="/etc/ssh/sshd_config.backup.$(date +%Y%m%d_%H%M%S)"

# Backup SSH config
cp "$SSH_CONFIG" "$SSH_CONFIG_BACKUP"
echo -e "${GREEN}SSH config backed up to: $SSH_CONFIG_BACKUP${NC}"

# Check if configuration already exists
if ! grep -q "Match User $USERNAME" "$SSH_CONFIG"; then
    cat >> "$SSH_CONFIG" << SSH_CONFIG_EOF

# Restricted access for user $USERNAME
Match User $USERNAME
    ChrootDirectory $USER_HOME/jail
    ForceCommand internal-sftp
    AllowTcpForwarding no
    X11Forwarding no
    PermitTunnel no
SSH_CONFIG_EOF
    echo -e "${GREEN}SSH configuration added for user $USERNAME${NC}"
    echo -e "${YELLOW}Note: You may need to restart SSH service: sudo systemctl restart sshd${NC}"
else
    echo -e "${YELLOW}SSH configuration for $USERNAME already exists${NC}"
fi

# Set proper permissions for chroot directory (must be owned by root)
chown root:root "$JAIL_DIR"
chmod 755 "$JAIL_DIR"

# Create a symlink or mount point for the actual folder
# The folder inside chroot should be writable by the user
mkdir -p "$JAIL_DIR/var/www"
chown "$USERNAME:$USERNAME" "$JAIL_DIR/var/www"

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
echo "2. Set password for user (optional): sudo passwd $USERNAME"
echo "3. Restart SSH service: sudo systemctl restart sshd"
echo "4. Test connection: ssh $USERNAME@your-server"
echo ""
echo -e "${YELLOW}Note:${NC} The user will be chrooted to $USER_HOME/jail"
echo "The actual folder is accessible at: /var/www/$(basename $FOLDER_PATH) inside the chroot"


