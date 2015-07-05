#!/bin/bash
#

WHERE=`pwd`

TGZ_NAME="ampcentral-client-2.1.1.tgz"
DIR_NAME="ampcentral-client"
./sdk.sh

cd ..
tar -cvz --exclude=OLD --exclude=*.webprj --exclude=work --exclude=*~ --exclude=CVS --exclude=.?* --exclude=np --exclude=.cvsignore -f $TGZ_NAME $DIR_NAME
cd "$WHERE"
