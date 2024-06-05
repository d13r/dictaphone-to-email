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

Then:

```bash
cp .env.example .env
vim .env
```

Create an IAM policy (see [iam-policy.json](iam-policy.json)), user and access keys.

Create an S3 bucket. Make a lifecycle rule to delete files automatically after 1 day, in case the automatic cleanup fails.

Create the following shortcut:

```bash
wt.exe --profile Ubuntu wsl.exe --distribution Ubuntu ~/dictaphone-to-email/dictaphone-to-email && read -p 'Press Enter to continue...'
```
