#!/bin/bash

cd "$(dirname "$0")"
. ./.config.cfg

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

BUCKET=$google_bucket
BACKUP_DIR=$backups_path

BEGIN=$(date +%s.%N)
DOTM=$(date +"%d")
TIMESTAMP=$(date +"%A")
HOSTNAME=$(hostname)


echo
echo "$(/bin/date +'%A, %d %b %Y %H:%M:%S') Starting backup"
echo

# vhosts backup
VHOSTS=$vhosts_path
DOMAINS=`ls $VHOSTS`

mkdir -p "$BACKUP_DIR/vhosts"

for vhost in $DOMAINS; do
    START=$(date +%s.%N)
    echo -n $(date +"%H:%M:%S") "Backing up: $vhost - "

    /bin/rm -f $BACKUP_DIR/$vhost.tar.gz
    /bin/tar --exclude='logs/*' --exclude='tmp/*' -czf $BACKUP_DIR/$vhost.tar.gz -C $VHOSTS/$vhost/ .

    # move backup file to the cloud
    $gsutil mv $BACKUP_DIR/$vhost.tar.gz gs://$BUCKET/$TIMESTAMP/$vhost.tar.gz

    # move backup file to backup host
    # /usr/bin/scp -q $backups_path/$vhost.tar.gz backup.host:$backups_path/vhosts/
    # /bin/rm -f $backups_path/$vhost.tar.gz

    END=$(date +%s.%N)
    dt=$(echo "$END - $START" | bc)
    dd=$(echo "$dt/86400" | bc)
    dt2=$(echo "$dt-86400*$dd" | bc)
    dh=$(echo "$dt2/3600" | bc)
    dt3=$(echo "$dt2-3600*$dh" | bc)
    dm=$(echo "$dt3/60" | bc)
    ds=$(echo "$dt3-60*$dm" | bc)

    printf "Runtime: %02d:%02.2f sec \n\n" $dm $ds
done



# Runtime
END=$(date +%s.%N)
dt=$(echo "$END - $BEGIN" | bc)
dd=$(echo "$dt/86400" | bc)
dt2=$(echo "$dt-86400*$dd" | bc)
dh=$(echo "$dt2/3600" | bc)
dt3=$(echo "$dt2-3600*$dh" | bc)
dm=$(echo "$dt3/60" | bc)
ds=$(echo "$dt3-60*$dm" | bc)

printf "$(/bin/date +'%A, %d %b %Y %H:%M:%S') Backup completed. Total runtime: %d:%02d:%02d:%02.4f\n" $dd $dh $dm $ds
echo
