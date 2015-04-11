#!/bin/bash

#
# Joomla-updater
# Copyright (C) 2015 Eleven BV
# www.eleven.nl
# 
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
#

BASEDIR=`pwd`

# loop project dirs in this parent dir
for PROJECTDIR in * ; do
    if [ -d "${PROJECTDIR}" ]; then
        cd ${BASEDIR}/${PROJECTDIR}

        # loop repositories in this project dir
        for REPODIR in * ; do
            if [ -d "${REPODIR}" ]; then
                cd ${BASEDIR}/${PROJECTDIR}/${REPODIR}
                echo "Checking for updates in ${PROJECTDIR}/${REPODIR}"

                # first refresh the repository
                git pull

                # call the joomla update script
                php ${BASEDIR}/update_joomla.php path=${BASEDIR}/${PROJECTDIR}/${REPODIR}

                # if there are changes, push them
                git add .
                git commit -m "Updated Joomla to latest version"
                git push origin master

                cd ${BASEDIR}/${PROJECTDIR}
            fi
        done

        cd ${BASEDIR}
    fi
done

