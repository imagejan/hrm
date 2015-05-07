#!/usr/bin/env python

"""OMERO connector for the Huygens Remote Manager (HRM).

This wrapper processes all requests from the HRM web interface to communicate
to an OMERO server for listing available images, transferring data, etc.
"""


import sys
import argparse
import os
from omero.gateway import BlitzGateway
import json


# possible actions (this will be used when showing the help message on the
# command line later on as well, so keep this in mind when formatting!)
ACTIONS = """actions:
  checkCredentials      Check if login credentials are valid.
  retrieveUserTree      Get a user's Projects/Datasets/Images tree (JSON).
  OMEROtoHRM            Download an image from the OMERO server.
  HRMtoOMERO            Upload an image to the OMERO server.
"""


# the default connection values
HOST = 'omero.mynetwork.xy'
PORT = 4064
USERNAME = 'foo'
PASSWORD = 'bar'

# allow overriding the default values
if "OMERO_HOST" in os.environ:
    HOST = os.environ['OMERO_HOST']
if "OMERO_PORT" in os.environ:
    PORT = os.environ['OMERO_PORT']
if "OMERO_USER" in os.environ:
    USERNAME = os.environ['OMERO_USER']
if "OMERO_PASS" in os.environ:
    PASSWORD = os.environ['OMERO_PASS']


def iprint(text, indent=0):
    """Helper method for intented printing."""
    print('%s%s' % (" " * indent, text))


def omero_login(user, passwd, host, port):
    """Establish the connection to an OMERO server."""
    conn = BlitzGateway(user, passwd, host=host, port=port)
    conn.connect()
    return conn


def tree_to_json(obj_tree):
    """Create a JSON object with a given format from a tree."""
    return json.dumps(obj_tree, sort_keys=True,
                      indent=4, separators=(',', ': '))


def get_group_tree_json(conn, group):
    """Generates the group tree and returns it in JSON format."""
    # TODO: this is probably also required for a user's sub-tree
    # we're currently only having a single tree (dict), but jqTree expects a
    # list of dicts, so we have to encapsulate it in [] for now:
    print(tree_to_json([gen_group_tree(conn, group)]))


def gen_obj_dict(obj):
    """Create a dict from an OMERO object.

    Structure
    =========
    {
        'children': [],
        'id': 1154L,
        'label': 'HRM_TESTDATA',
        'owner': u'demo01',
        'class': 'Project'
    }
    """
    obj_dict = dict()
    obj_dict['id'] = obj.getId()
    obj_dict['label'] = obj.getName()
    # TODO: it's probably better to store the owner's ID instead of the name
    obj_dict['owner'] = obj.getOwnerOmeName()
    obj_dict['class'] = obj.OMERO_CLASS
    obj_dict['children'] = []
    return obj_dict


def gen_image_dict(image):
    """Create a dict from an OMERO image.

    Structure
    =========
    {'id': 1755L, 'label': 'Rot-13x-zstack.tif', 'owner': u'demo01'}
    """
    if image.OMERO_CLASS is not 'Image':
        raise ValueError
    image_dict = dict()
    image_dict['id'] = image.getId()
    image_dict['label'] = image.getName()
    # TODO: it's probably better to store the owner's ID instead of the name
    image_dict['owner'] = image.getOwnerOmeName()
    return image_dict


def gen_proj_tree(conn, user_obj):
    """Create a list of project trees for a user.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    user_obj : omero.gateway._ExperimenterWrapper
    """
    proj_tree = []
    for project in conn.listProjects(user_obj.getId()):
        proj_dict = gen_obj_dict(project)
        for dataset in project.listChildren():
            dset_dict = gen_obj_dict(dataset)
            for image in dataset.listChildren():
                dset_dict['children'].append(gen_image_dict(image))
            proj_dict['children'].append(dset_dict)
        proj_tree.append(proj_dict)
    return proj_tree


def gen_user_tree(conn, user_obj):
    """Create a tree with user information and corresponding projects.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    user_obj : omero.gateway._ExperimenterWrapper

    Returns
    =======
    {
        "id": (int, e.g. 14),
        "label": (str, e.g. "01 Demouser"),
        "ome_name": (str, e.g. "demo01"),
        "children": proj_tree (list)
    }
    """
    user_dict = dict()
    uid = user_obj.getId()
    user_dict['id'] = uid
    user_dict['label'] = user_obj.getFullName()
    user_dict['ome_name'] = user_obj.getName()
    user_dict['children'] = gen_proj_tree(conn, user_obj)
    return user_dict


def gen_group_tree(conn, group_obj):
    """Create a tree for a group with all user subtrees.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    group_obj : omero.gateway._ExperimenterGroupWrapper

    Returns
    =======
    {
        "id": (int, e.g. 9),
        "label": (str, e.g. "Sandbox Lab"),
        "children": user_trees (list of dict))
    }
    """
    group_dict = dict()
    group_dict['id'] = group_obj.getId()
    group_dict['label'] = group_obj.getName()
    group_dict['description'] = group_obj.getDescription()
    group_dict['children'] = []
    # add the user's own tree first:
    user_obj = conn.getUser()
    user_tree = gen_user_tree(conn, user_obj)
    group_dict['children'].append(user_tree)
    # then add the trees for other group members
    for colleague in conn.listColleagues():
        user_tree = gen_user_tree(conn, colleague)
        group_dict['children'].append(user_tree)
    return group_dict


def parse_arguments():
    """Parse the commandline arguments."""
    argparser = argparse.ArgumentParser(
        description=__doc__,
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=ACTIONS
    )
    argparser.add_argument(
        'action', choices=['checkCredentials',
                           'retrieveUserTree',
                           'OMEROtoHRM',
                           'HRMtoOMERO'],
        help='Action to be performed by the connector, see below for details.')
    argparser.add_argument(
        '-u', '--user', required=True, help='OMERO username')
    argparser.add_argument(
        '-w', '--password', required=True, help='OMERO password')
    argparser.add_argument(
        '-v', '--verbose', dest='verbosity', action='count', default=0,
        help='verbosity (repeat for more details)')
    try:
        return argparser.parse_args()
    except IOError as err:
        argparser.error(str(err))


def check_credentials(conn, group):
    """Check if supplied credentials are valid."""
    # TODO: do we really need this function...?
    connected = conn.connect()
    if connected:
        print('Success logging into OMERO with user ID %s' % conn.getUserId())
    else:
        print('ERROR logging into OMERO.')
    return connected


def omero_to_hrm(conn, group, image_id):
    from omero.rtypes import unwrap
    from omero.sys import ParametersI
    from omero_model_OriginalFileI import OriginalFileI as OFile
    session = conn.c.getSession()
    query = session.getQueryService()
    params = ParametersI()
    params.addLong('iid', image_id)
    sql = "select f from Image i" \
        " left outer join i.fileset as fs" \
        " join fs.usedFiles as uf" \
        " join uf.originalFile as f" \
        " where i.id = :iid"
    query_out = query.projection(sql, params, {'omero.group': '-1'})
    file_id = unwrap(query_out[0])[0].id.val
    print file_id
    orig_file = OFile(file_id)
    # conn.c.download(orig_file, '/tmp/OMERO_python_download_test')


def hrm_to_omero(conn, group):
    pass


def main():
    """Parse commandline arguments and initiate the requested tasks."""
    # create a dict with the functions to call
    action_methods = {
        'checkCredentials': check_credentials,
        'retrieveUserTree': get_group_tree_json,
        'OMEROtoHRM': omero_to_hrm,
        'HRMtoOMERO': hrm_to_omero
    }

    conn = omero_login(USERNAME, PASSWORD, HOST, PORT)
    # if not requested other, we're just using the default group
    group_obj = conn.getGroupFromContext()
    # TODO: implement requesting groups via cmdline option

    args = parse_arguments()
    action_methods[args.action](conn, group_obj)


if __name__ == "__main__":
    sys.exit(main())
