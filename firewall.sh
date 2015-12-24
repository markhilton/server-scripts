#!/bin/bash

. ./.config.cfg

/sbin/iptables -F
/sbin/iptables -A INPUT -p tcp --tcp-flags ALL NONE -j DROP
/sbin/iptables -A INPUT -p tcp ! --syn -m state --state NEW -j DROP
/sbin/iptables -A INPUT -p tcp --tcp-flags ALL ALL -j DROP


/sbin/iptables -A INPUT -i lo -j ACCEPT
/sbin/iptables -A INPUT -p tcp -m tcp --dport 80 -j ACCEPT
/sbin/iptables -A INPUT -p tcp -m tcp --dport 443 -j ACCEPT



for i in "${firewall[@]}"
do
    /sbin/iptables -A INPUT -s $i -j ACCEPT
done


/sbin/iptables -I INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
/sbin/iptables -P OUTPUT ACCEPT
/sbin/iptables -P INPUT DROP
/sbin/iptables -L -n

/sbin/iptables-save > /etc/iptables/rules.v4
/etc/init.d/iptables-persistent restart

