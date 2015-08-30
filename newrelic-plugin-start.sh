#!/bin/bash

killall -9 newrelic-plugin-agent
newrelic-plugin-agent -c /etc/newrelic/newrelic-plugin-agent.cfg
