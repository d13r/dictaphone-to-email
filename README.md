# Dictaphone to Email

## Setup

```bash
git clone git@github.com:d13r/dictaphone-to-email.git
cd dictaphone-to-email
composer install
sudo visudo /etc/sudoers.d/dictaphone-to-email
```

Enter:

```bash
dave ALL=(ALL) NOPASSWD:/usr/bin/mkdir -p /mnt/r
dave ALL=(ALL) NOPASSWD:/usr/bin/mount -t drvfs r\: /mnt/r
dave ALL=(ALL) NOPASSWD:/usr/bin/umount /mnt/r
dave ALL=(ALL) NOPASSWD:/usr/bin/rmdir /mnt/r
```
