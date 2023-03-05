# Dictaphone to Email

## Setup

```bash
git clone git@github.com:d13r/dictaphone-to-email.git
cd dictaphone-to-email
composer install
visudo /etc/sudoers.d/dictaphone-to-email
```

Enter:

```bash
dave ALL=(ALL) NOPASSWD:/usr/bin/mkdir -p /mnt/e
dave ALL=(ALL) NOPASSWD:/usr/bin/mount -t drvfs e\: /mnt/e
dave ALL=(ALL) NOPASSWD:/usr/bin/umount /mnt/e
dave ALL=(ALL) NOPASSWD:/usr/bin/rmdir /mnt/e
```
