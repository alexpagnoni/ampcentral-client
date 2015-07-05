#!/bin/sh
#

MOD_NAME=ampcentral-client
DOCS_DIR=doc/sdk/autodocs

if [ ! -d ${DOCS_DIR} ]
then
	mkdir -p ${DOCS_DIR}
fi

rm -rf ${DOCS_DIR}/*
cat var/lib/*.library var/handlers/*.element | sed 's/ *{/;#{/g' | tr "#" "\n" >${DOCS_DIR}/${MOD_NAME}.php
headerdoc2html -o ${DOCS_DIR} ${DOCS_DIR}/${MOD_NAME}.php
rm ${DOCS_DIR}/${MOD_NAME}.php
mv ${DOCS_DIR}/${MOD_NAME}/* ${DOCS_DIR}
rm -rf ${DOCS_DIR}/${MOD_NAME}
