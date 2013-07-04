#!/usr/bin/env python
# -*- coding: utf-8 -*-#
# @(#)HRM_QueueManager.py
#
"""
The prototype of a new GC3Pie-based Queue Manager for HRM.
"""

# TODO:
# - monitor a "new" directory via pyinotify
# - read in job files via ConfigParser
# - assemble a gc3libs.Application
# - add them to the queue

# stdlib imports
import sys
import time

# GC3Pie imports
import gc3libs

import ConfigParser

import logging
# loglevel = logging.DEBUG
loglevel = logging.WARN
gc3libs.configure_logger(loglevel, "qmgc3")
warn = gc3libs.log.warn

def parse_jobfile(fname):
    '''Parse details for an HRM job and check for sanity.
    .
    Take a job description file and assemble a dicitonary with the collected
    information that contains all the information for launching a new hucore
    processing task. Raises Exceptions in case something unexpected is found
    in the given file.
    '''
    # FIXME: currently only deconvolution jobs are supported!
    job = {}
    jobparser = ConfigParser.RawConfigParser()
    jobparser.read(jobfname)
    sections = jobparser.sections()
    # parse generic information, version, user etc.
    if not 'hrmjobfile' in sections:
        raise Exception("Error parsing job '%s'" % jobfname)
    try:
        job['ver'] = jobparser.get('hrmjobfile', 'version')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find version in '%s'" % jobfname)
    # TODO: check the version number and parse accordingly...
    try:
        job['user'] = jobparser.get('hrmjobfile', 'username')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find username in '%s'" % jobfname)
    # now parse the deconvolution section
    try:
        job['templ'] = jobparser.get('deconvolution', 'template')
    except ConfigParser.NoOptionError:
        raise Exception("Can't find template in '%s'" % jobfname)

    # and the input file(s)
    section = 'inputfiles'
    if not section in sections:
        raise Exception("No input files defined in '%s'" % jobfname)
    job['infiles'] = []
    for option in jobparser.options('inputfiles'):
        infile = jobparser.get(section, option)
        job['infiles'].append(infile)
    warn(job)
    return job

jobfname = 'spool/examples/deconvolution_job.cfg'
parse_jobfile(jobfname)
sys.exit()


class HucoreDeconvolveApp(gc3libs.Application):
    """
    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """
    # TODO: parametrize this class!
    def __init__(self):
        gc3libs.Application.__init__(
            self,
            arguments = ["/usr/local/bin/hucore",
                '-template', 'hucore_template_relative.tcl'],
            inputs = ['./hucore_template_relative.tcl', 'bad.lsm'],
            outputs = ['resultdir', 'previews'],
            output_dir = './deconvolved',
            stderr = 'stdout.txt', # combine stdout & stderr
            stdout = 'stdout.txt')

# Create an instance of HucoreDeconvolveApp
app = HucoreDeconvolveApp()

# Create an instance of `Engine` using the configuration file present in your
# home directory.
engine = gc3libs.create_engine()

# Add your application to the engine. This will NOT submit your application
# yet, but will make the engine *aware* of the application.
engine.add(app)

# in case you want to select a specific resource, call
# `Engine.select_resource(<resource_name>)`
if len(sys.argv)>1:
    engine.select_resource(sys.argv[1])

# Periodically check the status of your application.
while app.execution.state != gc3libs.Run.State.TERMINATED:
    print "Job in status %s " % app.execution.state
    # `Engine.progress()` will do the GC3Pie magic: submit new jobs, update
    # status of submitted jobs, get results of terminating jobs etc...
    engine.progress()

    # Wait a few seconds...
    time.sleep(1)

print "Job is now terminated."
print "The output of the application is in `%s`." %  app.output_dir