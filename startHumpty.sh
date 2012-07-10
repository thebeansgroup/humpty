#!/bin/sh

#############################################
# Shell script for launching Humpty
#
#  Copyright 2010 studentbeans.com
#  All rights reserved.
#
#  This file is part of Humpty.
#
#  Humpty is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  Humpty is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with Humpty.  If not, see <http://www.gnu.org/licenses/>.
#
# To use this, just add a line to your /etc/rc.local file 
# calling this script.
#############################################

BASE_DIR=/usr/local/humpty
SCRIPT=$BASE_DIR/humptyd
PID_FILE=/var/run/humptyd.pid

# If there is an old process, kill it
kill `cat $PID_FILE`
# Make sure the file is clean
rm -f $PID_FILE

cd $BASE_DIR
nohup $SCRIPT > /dev/null &
PID=$!

echo $PID > $PID_FILE
