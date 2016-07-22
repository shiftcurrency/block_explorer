#!/usr/bin/env bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
running=$(ps aux |grep gshift |wc -l);
if [[ $running -eq 3 ]]; then
    echo "starting gshift via script."
    /home/joey/gshift-linux-amd64 --rpc --rpccorsdomain "http://explorer.shiftnrg.org"
fi
