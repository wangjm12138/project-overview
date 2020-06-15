import mysql.connector
import requests
import json
import os
import sys
import re
from api.api_common import api_calls
from helper.common import common_functions
from datetime import datetime
import timeit
import logging

os.chdir(os.path.dirname(os.path.abspath(sys.argv[0])))

# Used for logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
handler = logging.FileHandler("logs/" + datetime.now().strftime('log_%d_%m_%Y.log'))
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

# Jama details
base_url = "https://jabra.jamacloud.com/rest/v1/"
# Read only user
username = "mkamhieh"
password = "50427221M"


def checkFolders():
    if not os.path.exists("db/jama/"):
        os.makedirs("db/jama")
    if not os.path.exists("logs"):
        os.makedirs("logs")


def getProjects():
    """ Function used to get project ID """
    existing_projects = []
    resources = "projects"
    allowed_results = 10
    max_results = "maxResults=" + str(allowed_results)
    result_count = -1
    start_index = 0
    while result_count != 0:
        start_at = "startAt=" + str(start_index)
        url = base_url + resources + "?" + start_at + "&" + max_results
        response = requests.get(url, auth=(username, password))
        json_response = json.loads(response.text)
        # Processing meta side of response
        page_info = json_response["meta"]["pageInfo"]
        start_index = page_info["startIndex"] + allowed_results
        result_count = page_info["resultCount"]
        # Processing data side of response
        json_response_data = json_response["data"]
        for project in json_response_data:
            existing_projects.append(
                {"name": str(project["fields"]["name"]), "id": project["id"], "status": project["fields"]["statusId"]})
    return existing_projects


def getTestPlans(project_id):
    """ Function used to get testplans """
    existing_test_plans = {}
    resources = "testplans?project=%s" % project_id
    allowed_results = 10
    max_results = "maxResults=" + str(allowed_results)
    result_count = -1
    start_index = 0
    while result_count != 0:
        start_at = "startAt=" + str(start_index)
        url = base_url + resources + "&" + start_at + "&" + max_results
        response = requests.get(url, auth=(username, password))
        json_response = json.loads(response.text)
        # Processing meta side of response
        page_info = json_response["meta"]["pageInfo"]
        start_index = page_info["startIndex"] + allowed_results
        result_count = page_info["resultCount"]
        # Processing data side of response
        json_response_data = json_response["data"]
        for test_plan in json_response_data:
            existing_test_plans[test_plan["id"]] = {"name": str(re.sub('[^\w\-_\. ]', "", test_plan["fields"]["name"])),
                                                    "id": test_plan["id"], "archived": test_plan["archived"]}
    return existing_test_plans


def logData(input):
    """Save log"""
    print(input)
    log = open("logs/" + datetime.now().strftime('log_%d_%m_%Y.log'), "a+")
    log.write(datetime.now().strftime('%H:%M:%S') + " [JAMA]: " + input + "\n")
    log.close()


def getType(type):
    """Function to get item type"""
    for item_type in item_types:
        if item_type["type_key"].lower() == type.lower():
            return item_type["id"]


def getTeam(item):
    """Get team name for an item"""
    if "sequence" in item["location"]:
        if item["location"]["sequence"][0] in sequence:
            return sequence[item["location"]["sequence"][0]]
        parentid = rest_api.getParent(item["location"]["parent"]["item"])
        if parentid == "Unspecified":
            return "Unassigned"
        if '.' in parentid["location"]["sequence"]:
            while True:
                parentid = rest_api.getParent(parentid["id"])
                try:
                    if not '.' in parentid["location"]["sequence"]:
                        sequence[parentid["location"]["sequence"]] = parentid["fields"]["name"]
                        return parentid["fields"]["name"]
                except TypeError:
                    return "Unassigned"
        else:
            return parentid["fields"]["name"]
    else:
        return "Unassigned"


def getrel(item):
    """Get rel name for an item"""
    if "rel" in item["fields"]:
        relid = item["fields"]["rel"]
        if not relid in customtext:
            customtext[relid] = {"name": rest_api.getrel(relid)}
        rel = customtext[relid]["name"].title()
        if rel == "Unassigned" or rel == "Unknown":
            rel = "Unspecified"
    else:
        rel = "Unspecified"
    return rel


def getText(item):
    """Translate an item id to text"""
    if not item in customtext:
        customtext[item] = {"name": rest_api.getStatus(item)}
    return customtext[item]["name"]


def getFeatures():
    """Get data for features"""
    logData("Processing features for %s" % project_name)
    if "features" in tables:
        temp = rest_api.getChanges(project["id"], getType("feat"))
        if len(temp) != 0:
            storeFeatures(temp, "change")
        else:
            logData("No changes for features")
            storeFeatures(temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project["id"], getType("feat"))
        if len(temp) != 0:
            storeFeatures(temp, "new")


def storeFeatures(data, type):
    logData("Storing features for %s" % project_name)
    c = mydb.cursor()
    c.execute("CREATE TABLE IF NOT EXISTS features (rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS allfeatures (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT)")
    c.execute("DELETE FROM features WHERE date='{0}'".format(str(datetime.now().date())))
    for feat in data:
        if type == "change":
            c.execute("DELETE FROM allfeatures WHERE id=%d" % feat["id"])
        val = (feat["id"], feat["documentKey"], feat["fields"]["name"], getText(feat["fields"]["status"]).title(), getrel(feat))
        c.execute("INSERT INTO allfeatures (id, uniqueid, name, status, rel) VALUES (%s, %s, %s, %s, %s)", val)
    c.execute(
        "INSERT INTO features (rel, status, count, date) SELECT rel, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM allfeatures cross join (select @rn := 0) const GROUP BY rel, status".format(
            str(datetime.now().date())))

    """Delete deleted items from DB"""
    deleted = rest_api.getDeletedItems(project["id"], getType("feat"))
    for delItems in deleted:
        c.execute("DELETE FROM allfeatures WHERE id = %d" % delItems["item"])
    mydb.commit()
    logData("Done storing features for %s" % project_name)


def getChangeRequests():
    """Get data for change requests"""
    logData("Processing change requests for %s" % project_name)
    if "changes" in tables:
        temp = rest_api.getChanges(project["id"], getType("cr"))
        if len(temp) != 0:
            storeChangeRequests(temp, "change")
        else:
            logData("No changes for change requests")
            storeChangeRequests(temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project["id"], getType("cr"))
        if len(temp) != 0:
            storeChangeRequests(temp, "change")


def storeChangeRequests(data, type):
    """Store change requests"""
    logData("Storing change requests for %s" % project_name)
    c = mydb.cursor()
    c.execute(
        "CREATE TABLE IF NOT EXISTS changes (rel TEXT, status TEXT, priority TEXT, requester TEXT, date TEXT, count INT)")
    c.execute(
        "CREATE TABLE IF NOT EXISTS allchanges (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, priority TEXT, requester TEXT)")
    c.execute("DELETE FROM changes WHERE date='{0}'".format(str(datetime.now().date())))
    for change in data:
        status = getText(change["fields"]["status"]).title()
        rel = getrel(change)
        try:
            priority = getText(change["fields"]["priority"])
        except:
            priority = ""
        if 'string1' in change["fields"]:
            req = change["fields"]["string1"]
        else:
            req = "Unassigned"
        if type == "change":
            c.execute("DELETE FROM allchanges WHERE id=%d" % change["id"])
        val = (change["id"], change["documentKey"], change["fields"]["name"], status, rel, priority, req)
        c.execute("INSERT INTO allchanges (id, uniqueid, name, status, rel, priority, requester) VALUES (%s, %s, %s, %s, %s, %s, %s)",val)
    c.execute(
        "INSERT INTO changes (rel, status, priority, requester, date, count) SELECT rel, status, priority, requester, '{0}' as date, count((@rn := @rn + 1)) as count FROM allchanges cross join (select @rn := 0) const "
        "GROUP BY rel, status, priority, requester".format(str(datetime.now().date())))
    mydb.commit()
    logData("Done storing change requests for %s" % project_name)


def getDefects():
    """"Get data for defects"""
    logData("Processing defects for %s" % project_name)
    if "defects" in tables:
        temp = rest_api.getChanges(project["id"], getType("bug"))
        if len(temp) != 0:
            storeDefects(temp, "change")
        else:
            logData("No changes for defects")
            storeDefects(temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project["id"], getType("bug"))
        if len(temp) != 0:
            storeDefects(temp, "new")


def storeDefects(data, type):
    # Create required sql tables
    logData("Storing defects for %s" % project_name)
    c = mydb.cursor()
    c.execute(
        "CREATE TABLE IF NOT EXISTS defects (rel TEXT, team TEXT, status TEXT, priority TEXT, count INT, date TEXT)")
    c.execute(
        "CREATE TABLE IF NOT EXISTS alldefects (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, team TEXT, priority TEXT, jira TEXT, upstream TEXT)")
    c.execute("DELETE FROM defects WHERE date='{0}'".format(str(datetime.now().date())))

    """Delete deleted items from DB"""
    deleted = rest_api.getDeletedItems(project["id"], getType("bug"))
    for delItems in deleted:
        c.execute("DELETE FROM alldefects WHERE id = %d" % delItems["item"])

    for defect in data:
        upstream = ""
        upstreamrelations = rest_api.getUpstreamRelationships(defect["id"])
        status = getText(defect["fields"]["status"]).title()
        rel = getrel(defect)
        priority = getText(defect["fields"]["priority"])
        for n in upstreamrelations:
            upstream += str(n["fromItem"]) + ", "
        try:
            teamid = defect["fields"]["responsible_function$89012"]
            if not teamid in customtext:
                customtext[teamid] = {"name": rest_api.getStatus(teamid)}
            team = customtext[teamid]["name"]
            for tm in allTeams:
                if team in allTeams[tm]:
                    team = tm
                    break
        except:
            team = "Unassigned"
        if "jink_to_jira$89012" in defect["fields"]:
            jira = defect["fields"]["jink_to_jira$89012"]
        else:
            jira = ""
        if type == "change":
            c.execute("DELETE FROM alldefects WHERE id=%d" % defect["id"])
        val = (defect["id"], defect["documentKey"], defect["fields"]["name"], status, rel, team, priority, jira, upstream[:-2])
        c.execute("INSERT INTO alldefects (id, uniqueid, name, status, rel, team, priority, jira, upstream) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",val)
    c.execute(
        "INSERT INTO defects (rel, team, status, count, date, priority) SELECT rel, team, status, count((@rn := @rn + 1)) as count, '{0}' as date, priority FROM alldefects cross join (select @rn := 0) const "
        "GROUP BY rel, team, status, priority".format(str(datetime.now().date())))
    mydb.commit()
    logData("Done storing defects for %s" % project_name)


def getTests():
    """Get data for testcases"""
    processedTests = []
    if len(existing_testplans) != 0:
        logData("Processing test plans, test approval status for %s" % project_name)
        for testplan in existing_testplans:
            # Checking if testplan is archived
            if not existing_testplans[testplan]["archived"]:
                testdata[testplan] = {"testgroup": {}, "testcycles": rest_api.getTestCycles(testplan), "overall": {}}
                # Finding test groups in a test plan
                test_groups = rest_api.getTestGroups(testplan)
                # Getting test cases per each test group in a test plan
                testdata[testplan]["name"] = existing_testplans[testplan]["name"]
                # Getting test cases per each test group in a test plan
                for test_group in test_groups:
                    testdata[testplan]["testgroup"][test_group] = {"id": test_group,
                                                                   "name": test_groups[test_group]["name"]}
                    try:
                        testdata[testplan]["testgroup"][test_group]["testcases"] = rest_api.getTestCases(testplan,
                                                                                                         test_group)
                    except Exception as e:
                        logger.exception("Error: pageInfo exception", e)
                        continue


                # Get latest test run data
                logData("Getting test runs for %s" % testdata[testplan]["name"])
                getTestRuns(testplan)
        # Save and sort testcases by testplans
        for testplan in testdata:
            for testgroup in testdata[testplan]["testgroup"]:
                for testcase in testdata[testplan]["testgroup"][testgroup]["testcases"]:
                    case = testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]
                    rel = getrel(case)
                    upstreamrelations = rest_api.getUpstreamRelationships(testcase)
                    case["upstream"] = ""
                    for n in upstreamrelations:
                        case["upstream"] += str(n["fromItem"]) + ", "
                    # Avoid saving wrong data. Clean up the executionDates if it matches the following status
                    if case["fields"]["testCaseStatus"] != "NOT_RUN" and "executionDate" not in case["fields"]:
                        case["fields"]["testCaseStatus"] = "NOT_SCHEDULED"
                    if case["fields"]["testCaseStatus"] == "NOT_RUN" or case["fields"][
                        "testCaseStatus"] == "SCHEDULED" or case["fields"]["testCaseStatus"] == "NOT_SCHEDULED":
                        if "executionDate" in case["fields"]:
                            del case["fields"]["executionDate"]
                    if case["fields"]["testCaseStatus"] == "NOT_RUN":
                        case["fields"]["testCaseStatus"] = "SCHEDULED"
                    """ Test approval """
                    status = case["fields"]["test_case_approval_status$89011"] if "test_case_approval_status$89011" in \
                                                                                  case["fields"] else ""
                    team = getTeam(case)
                    if len(str(status)) > 1 and status not in customtext:
                        customtext[status] = {"name": rest_api.getStatus(status).lower()}
                    status = customtext[status]["name"].title() if len(str(status)) > 1 else ""
                    # Save the test case in raw format
                    if not case["id"] in processedTests:
                        processedTests.append(case["id"])
                        testapproval.append((case["id"], case["documentKey"], case["fields"]["name"], status, rel,
                                             team, case["upstream"][:-2]))


def getTestRuns(testplan):
    """Get data for test maturity"""
    testruns = rest_api.getTestRunsByTestplan(testplan)
    if testruns:
        for testrun in testruns:
            testcase = testrun["fields"]["testCase"]
            testcycle = testrun["fields"]["testCycle"]
            for testgroup in testdata[testplan]["testgroup"]:
                if testcase in testdata[testplan]["testgroup"][testgroup]["testcases"]:
                    if testrun["createdDate"] > testdata[testplan]["testgroup"][testgroup]["testcases"][testcase][
                        "createdDate"]:
                        tc = testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]
                        if "executionDate" in testrun["fields"]:
                            if not "executionDate" in tc:
                                tc["createdDate"] = testrun["createdDate"]
                                tc["executionDate"] = testrun["fields"]["executionDate"]
                                tc["testCaseStatus"] = testrun["fields"]["testRunStatus"]
                                tc["testCycle"] = testcycle
                            elif datetime.strptime(testrun["fields"]["executionDate"], "%Y-%m-%d") > datetime.strptime(
                                    tc["executionDate"], "%Y-%m-%d"):
                                tc["createdDate"] = testrun["createdDate"]
                                tc["executionDate"] = testrun["fields"]["executionDate"]
                                tc["testCaseStatus"] = testrun["fields"]["testRunStatus"]
                                tc["testCycle"] = testcycle
                        else:
                            tc["createdDate"] = testrun["createdDate"]
                            tc["testCaseStatus"] = testrun["fields"]["testRunStatus"]
                            tc["testCycle"] = testcycle


def storeTests():
    """ Store test plans """
    c = mydb.cursor()
    c.execute(
        "CREATE TABLE IF NOT EXISTS tests (testplan_id INT, testplan_name TEXT, testplan_status TEXT, rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("DROP TABLE IF EXISTS testcases")
    c.execute(
        "CREATE TABLE IF NOT EXISTS testcases (testplan_id INT, testplan_name TEXT, testgroup_id INT, testgroup_name TEXT, testcycle_id INT, testcycle_name TEXT, rel TEXT, id INT, uniqueid TEXT, name TEXT, status TEXT, upstream TEXT, downstream TEXT, executionDate TEXT)")
    c.execute("DELETE FROM tests WHERE date='{0}'".format(str(datetime.now().date())))
    c.execute("SELECT testplan_id FROM tests GROUP BY testplan_id")
    rows = c.fetchall()
    tpsql = [row[0] for row in rows]
    # Check for unarchived test plans, name changes and remove deleted testplans
    for testplan in existing_testplans:
        if testplan in tpsql:
            c.execute("SELECT testplan_name FROM tests WHERE testplan_id={0} LIMIT 1".format(testplan))
            tmp = c.fetchone()
            if tmp[0] != existing_testplans[testplan]["name"]:
                c.execute("UPDATE tests SET testplan_name='{0}' WHERE testplan_id={1}".format(
                    existing_testplans[testplan]["name"], testplan))
            if existing_testplans[testplan]["archived"]:
                c.execute("UPDATE tests SET testplan_status='Inactive' WHERE testplan_id={0}".format(testplan))
            if not existing_testplans[testplan]["archived"]:
                c.execute("UPDATE tests SET testplan_status='Active' WHERE testplan_id={0}".format(testplan))
        else:
            c.execute("DELETE FROM tests WHERE testplan_id={0}".format(testplan))
    mydb.commit()
    # Save test data
    for testplan in testdata:
        plan = testdata[testplan]
        for testgroup in plan["testgroup"]:
            for testcase in plan["testgroup"][testgroup]["testcases"]:
                path = plan["testgroup"][testgroup]["testcases"][testcase]
                path["fields"]["rel"] = getrel(path)
                if "testCycle" in path["fields"]:
                    if path["fields"]["testCycle"] in plan["testcycles"]:
                        testcycle = path["fields"]["testCycle"]
                        testcycle_name = plan["testcycles"][path["fields"]["testCycle"]]["fields"]["name"]
                    else:
                        testcycle = path["fields"]["testCycle"]
                        testcycle_name = path["fields"]["testCycle"]
                else:
                    testcycle = 0
                    testcycle_name = "N/A"
                if "executionDate" in path["fields"]:
                    val = (testplan, plan["name"], testgroup, plan["testgroup"][testgroup]["name"], testcycle,
                     testcycle_name, path["fields"]["rel"], testcase, path["documentKey"],
                     path["fields"]["name"], path["fields"]["testCaseStatus"], path["upstream"][:-2],
                     path["fields"]["executionDate"])
                    c.execute("INSERT INTO testcases (testplan_id, testplan_name, testgroup_id, testgroup_name, testcycle_id, testcycle_name, rel, id, uniqueid, name, status, upstream, executionDate) values (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",val)
                else:
                    val = (testplan, plan["name"], testgroup, plan["testgroup"][testgroup]["name"], testcycle,
                         testcycle_name, path["fields"]["rel"], testcase, path["documentKey"],
                         path["fields"]["name"], path["fields"]["testCaseStatus"], path["upstream"][:-2], "0")
                    c.execute(
                        "INSERT INTO testcases (testplan_id, testplan_name, testgroup_id, testgroup_name, testcycle_id, testcycle_name, rel, id, uniqueid, name, status, upstream, executionDate) values (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",val)
    mydb.commit()
    c.execute("INSERT INTO tests (testplan_id, testplan_name, testplan_status, rel, status, count, date) "
              "SELECT testplan_id, testplan_name, 'Active' as testplan_status, rel, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM testcases cross join (select @rn := 0) const GROUP BY testplan_id, rel, status".format(
        str(datetime.now().date())))
    mydb.commit()


def storeTestapproval():
    """ Store test approval """
    c = mydb.cursor()
    c.execute(
        "CREATE TABLE IF NOT EXISTS testapproval (rel TEXT, team TEXT, status TEXT, upstream TEXT, count INT, date TEXT)")
    c.execute("DROP TABLE IF EXISTS alltestapproval")
    c.execute(
        "CREATE TABLE IF NOT EXISTS alltestapproval (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, team TEXT, upstream TEXT)")
    try:
        c.execute("ALTER TABLE testapproval ADD upstream TEXT")
    except:
        pass
    c.execute("DELETE FROM testapproval WHERE date='{0}'".format(str(datetime.now().date())))
    mydb.commit()
    c.executemany(
        "INSERT INTO alltestapproval (id, uniqueid, name, status, rel, team, upstream) VALUES (%s, %s, %s, %s, %s, %s, %s)",
        testapproval)
    mydb.commit()
    c.execute(
        "INSERT INTO testapproval (rel, team, status, count, date, upstream) SELECT rel, team, status, count((@rn := @rn + 1)) as count, '{0}' as date, upstream FROM alltestapproval cross join (select @rn := 0) const GROUP BY rel, team, status, upstream".format(
            str(datetime.now().date())))
    mydb.commit()


def getUserstories():
    """Get data for user stories"""
    logData("Processing user stories for %s" % project_name)
    if "userstories" in tables:
        temp = rest_api.getChanges(project["id"], getType("sty"))
        if len(temp) != 0:
            storeUserstories(temp, "change")
        else:
            storeUserstories(temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project["id"], getType("sty"))
        if len(temp) != 0:
            storeUserstories(temp, "new")


def storeUserstories(data, type):
    """Store users stories"""
    logData("Storing user stories for %s" % project_name)
    c = mydb.cursor()
    c.execute("CREATE TABLE IF NOT EXISTS userstories (rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS alluserstories (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT)")
    c.execute("DELETE FROM userstories WHERE date='{0}'".format(str(datetime.now().date())))

    """Delete deleted items from DB"""
    deleted = rest_api.getDeletedItems(project["id"], getType("sty"))
    for delItems in deleted:
        c.execute("DELETE FROM alluserstories WHERE id = %d" % delItems["item"])

    for userstory in data:
        try:
            rel = getrel(userstory)
            status = getText(userstory["fields"]["status"]).title()
            if type == "change":
                c.execute("DELETE FROM alluserstories WHERE id=%d" % userstory["id"])
            val=(userstory["id"], userstory["documentKey"], userstory["fields"]["name"], status, rel)
            c.execute("INSERT INTO alluserstories (id, uniqueid, name, status, rel) VALUES (%s, %s, %s, %s, %s)",val)
        except:
            logData("No user stories found for %s" % project_name)
    mydb.commit()
    c.execute(
        "INSERT INTO userstories (rel, status, count, date) SELECT rel, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM alluserstories cross join (select @rn := 0) const GROUP BY rel, status".format(
            str(datetime.now().date())))
    mydb.commit()
    logData("Done storing user stories for %s" % project_name)


def TestOfFeaturesAndRequirements():
    c = mydb.cursor()
    statusses = list()
    missingCov = list()
    allreqstat = list()

    # Removes unnecessary symbols from string
    def split(ROW):
        row = (str(ROW).replace("'", ""))
        row = (row.replace("(", ""))
        row = (row.replace(")", ""))
        row = (row.replace(" ", ""))
        row = (row.replace(",", ""))
        return row

    # Checks if the column exists and creates one if not
    def checkIfColumnExists(table_name):
        isInTable = False
        c.execute("SHOW COLUMNS FROM %s LIKE 'test_status'" % table_name)
        for x in c:
            if x != None:
                isInTable = True

        if not isInTable:
            c.execute("ALTER TABLE %s ADD COLUMN test_status TEXT" % table_name)

    # Gets the status of the item depending on the type
    def getStatus(statusses, type):
        status = ""
        if (type == "req"):
            if "PASSED" in statusses and "FAILED" not in statusses and "BLOCKED" not in \
                    statusses and "SCHEDULED" not in statusses and "NOT_SCHEDULED" not in statusses:
                status = "PASSED"
            if "FAILED" in statusses and "BLOCKED" not in statusses and "SCHEDULED" \
                    not in statusses and "NOT_SCHEDULED" not in statusses:
                status = "FAILED"
            if "BLOCKED" in statusses:
                status = "INCOMPLETE TESTING"
            if "SCHEDULED" in statusses:
                status = "INCOMPLETE TESTING"
            if "NOT_SCHEDULED" in statusses:
                status = "INCOMPLETE TESTING"
        else:
            if "PASSED" in allreqstat and "FAILED" not in allreqstat and "INCOMPLETE TESTING" \
                    not in allreqstat and "MISSING TEST COVERAGE" not in allreqstat:
                status = "PASSED"
            if "FAILED" in allreqstat and "INCOMPLETE TESTING" not in allreqstat \
                    and "MISSING TEST COVERAGE" not in allreqstat:
                status = "FAILED"
            if "INCOMPLETE TESTING" in allreqstat:
                status = "INCOMPLETE TESTING"
            if "MISSING TEST COVERAGE" in allreqstat:
                status = "MISSING TEST COVERAGE"
        return status

    # Checks if the tables exist
    if "allfeatures" in tables:
        if "testcases" in tables:
            if "allcoverage" in tables:
                logData("Processing Test of Features for %s" % project_name)

                '''Test Of features'''
                # Gets all the requirements of a feature and checks the test status of them to determine the test status of the feature
                table_name = "allfeatures"
                checkIfColumnExists(table_name)
                c.execute("CREATE TABLE IF NOT EXISTS featuresTest (rel TEXT, test_status TEXT, date TEXT)")

                c.execute("SELECT id, rel FROM allfeatures")
                myresult = c.fetchall()
                for row in myresult:
                    featID = split(row[0])
                    rel = split(row[1])


                    c.execute("SELECT id FROM allrequirements WHERE upstream IN (%s)" % row[0])
                    myresult = c.fetchall()
                    for row1 in myresult:

                        c.execute("SELECT missing FROM allcoverage WHERE id LIKE %s",  ("%" + str(row1[0]) + "%",))
                        myresult = c.fetchall()
                        for row2 in myresult:
                            row2 = split(row2)
                            missingCov.append(row2)

                        c.execute("SELECT status FROM testcases WHERE upstream LIKE %s", ("%" + str(row1[0]) + "%",))
                        myresult = c.fetchall()
                        for row1 in myresult:
                            row1 = split(row1)
                            statusses.append(row1)
                        type = "req"
                        status = getStatus(statusses, type)
                        if missingCov and missingCov != ['']:
                            status = "MISSING TEST COVERAGE"
                        allreqstat.append(status)
                        statusses = list()
                        missingCov = list()
                    type = "feat"
                    status = getStatus(allreqstat, type)


                    val = (status.lower(), featID)
                    c.execute("UPDATE allfeatures SET test_status = %s WHERE id = %s", val)
                    if status != "":
                        val = (rel, status, str(datetime.now().date()))
                        c.execute("INSERT INTO featuresTest (rel, test_status, date) VALUES (%s,%s,%s)", val)
                mydb.commit()

                '''Test Of Requirements'''
                # Gets all the requirements and checks their test status
                logData("Processing Test of Requirements for %s" % project_name)
                statusses = list()
                missingCov = list()
                table_name = "allrequirements"
                checkIfColumnExists(table_name)
                c.execute("CREATE TABLE IF NOT EXISTS requirementsTest (rel TEXT, test_status TEXT, date TEXT)")

                c.execute('SELECT id, rel FROM allrequirements')
                myresult = c.fetchall()
                for row1 in myresult:
                    reqID = split(row1[0])
                    rel = split(row1[1])

                    c.execute("SELECT missing FROM allcoverage WHERE id LIKE %s", ("%" + str(row1[0]) + "%",))
                    myresult = c.fetchall()

                    for row2 in myresult:
                        row2 = split(row2)
                        missingCov.append(row2)

                    c.execute("SELECT status FROM testcases WHERE upstream LIKE %s", ("%" + str(row1[0]) + "%",))
                    myresult = c.fetchall()
                    for row4 in myresult:
                        row4 = split(row4)
                        statusses.append(row4)
                    type = "req"
                    status = getStatus(statusses, type)
                    if missingCov and missingCov != ['']:
                        status = "MISSING TEST COVERAGE"
                    allreqstat.append(status)
                    statusses = list()
                    missingCov = list()

                    val = (status.lower(), reqID)
                    c.execute("UPDATE allrequirements SET test_status = %s WHERE id = %s", val)

                    if status != "":
                        val = (rel, status, str(datetime.now().date()))
                        c.execute("INSERT INTO requirementsTest (rel, test_status, date) VALUES (%s,%s,%s)", val)
                mydb.commit()

                c.execute("CREATE TABLE IF NOT EXISTS feattest (rel TEXT, status TEXT, count TEXT, date TEXT)")
                c.execute("CREATE TABLE IF NOT EXISTS reqtest (rel TEXT, status TEXT, count TEXT, date TEXT)")

                status_list = ["Passed", "Failed", "Incomplete testing", "Missing Test Coverage"]

                for row3 in status_list:
                    count = "0"
                    row2 = row3.upper()
                    c.execute('SELECT DISTINCT rel FROM featuresTest')
                    myresult = c.fetchall()
                    for row in myresult:
                        row = split(row[0])

                        val = (row, row2)
                        c.execute('SELECT count(test_status) as count FROM featuresTest WHERE rel = %s AND test_status = %s', val)
                        myresult = c.fetchall()
                        for row1 in myresult:
                            count = split(row1)
                        if (count != "0"):
                            val = (row, row3, count, str(datetime.now().date()),)
                            c.execute("INSERT INTO feattest (rel, status, count, date) VALUES (%s,%s,%s,%s)", val)
                            mydb.commit()

                for row3 in status_list:
                    count = "0"
                    row2 = row3.upper()
                    c.execute('SELECT DISTINCT rel FROM requirementsTest')
                    myresult = c.fetchall()
                    for row in myresult:
                        row = split(row[0])

                        val = (row, row2)
                        c.execute('SELECT count(test_status) as count FROM requirementsTest WHERE rel = %s AND test_status = %s', val)
                        myresult = c.fetchall()
                        for row1 in myresult:
                            count = split(row1)
                        if (count != "0"):
                            val = (row, row3, count, str(datetime.now().date()),)
                            c.execute("INSERT INTO reqtest (rel, status, count, date) VALUES (%s,%s,%s,%s)", val)
                            mydb.commit()


                c.execute("drop table if exists featuresTest")
                c.execute("drop table if exists requirementsTest")
                mydb.commit()

def getRequirements():
    """Get data for requirements and test coverage"""
    temp = rest_api.getItemsAbstract(project["id"], getType("req"))
    coveredTeams = ["Industrial Design", "Strategic Alliances", "PM", "PMM"]  # Teams that don't require testcase
    if len(temp) != 0:
        logData("Processing requirements for %s" % project_name)
        for requirement in temp:
            # Get status and rel in text format
            status = getText(requirement["fields"]["status"]).title()
            rel = getrel(requirement)
            downstreams = rest_api.getDownStream(requirement["id"])
            downstreamrelations = rest_api.getDownstreamRelationships(requirement["id"])
            upstreamrelations = rest_api.getUpstreamRelationships(requirement["id"])
            fromitem = ""
            toitem = ""
            for n in upstreamrelations:
                fromitem += str(n["fromItem"]) + ", "
            for n in downstreamrelations:
                toitem += str(n["toItem"]) + ", "
            # Check involved teams
            if "verifying_teams_new$89009" in requirement["fields"]:
                teams = rest_api.getTeam(requirement["fields"]["verifying_teams_new$89009"])
                requirement["fields"]["verifying_teams_new$89009"] = teams
                requirements["missingTC"][requirement["fields"]["documentKey"]] = {"id": requirement["id"],
                                                                                   "name": requirement["fields"][
                                                                                       "name"], "status": status,
                                                                                   "rel": rel}
                requirements["missingTC"][requirement["fields"]["documentKey"]]["teams"] = teams
                # Test coverage requirements
                for team in teams:
                    if team in requirements["coverage"]:
                        requirements["coverage"][team]["expected"] += 1
                    else:
                        requirements["coverage"][team] = {"covered": 0, "expected": 1}
                    # Default teams that don't require test case
                    if team in coveredTeams:
                        requirement["fields"]["verifying_teams_new$89009"][team] = 1
                        requirements["coverage"][team]["covered"] += 1

                # Check if the teams have covered
                if len(downstreams) != 0:
                    for downstream in downstreams:
                        # Check if the downstream is a test case
                        if 'TC' in downstream["documentKey"]:
                            # Get the team responsible for this test case
                            verifyingteam = getTeam(downstream)
                            # Check if the verifying team has covered
                            for v in teams:
                                for parentTeam, team in allTeams.items():
                                    if verifyingteam == parentTeam and v in team and not \
                                    requirement["fields"]["verifying_teams_new$89009"][v] == 1:
                                        requirement["fields"]["verifying_teams_new$89009"][v] = 1
                                        requirements["coverage"][v]["covered"] += 1

                # Tjek TC for missing teams
                elif len(downstreams) != 0 and len(teams) != 0:
                    for downstream in downstreams:
                        if 'TC' in downstream["documentKey"]:
                            team = getTeam(downstream)
                            for parent, child in allTeams.items():
                                if team == parent:
                                    for v in child:
                                        if v in requirements["missingTC"][requirement["fields"]["documentKey"]][
                                            "teams"]:
                                            requirements["missingTC"][requirement["fields"]["documentKey"]]["teams"][
                                                v] = 1
            # Save the requirement in raw format
            requirements["raw"].append((requirement["id"], requirement["documentKey"], requirement["fields"]["name"],
                                        status, rel, fromitem[:-2], toitem[:-2]))


def storeRequirements():
    """Store requirements"""
    c = mydb.cursor()
    c.execute("CREATE TABLE IF NOT EXISTS requirements (rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("DROP TABLE IF EXISTS allrequirements")
    c.execute("DROP TABLE IF EXISTS allcoverage")
    c.execute("CREATE TABLE IF NOT EXISTS coverage (team text, covered int, expected int, date text)")
    c.execute("CREATE TABLE IF NOT EXISTS allrequirements (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, upstream TEXT, downstream TEXT, team TEXT)")
    c.execute(
        "CREATE TABLE IF NOT EXISTS allcoverage (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, verify TEXT, missing TEXT)")
    c.execute("DELETE FROM requirements WHERE date='{0}'".format(str(datetime.now().date())))
    c.execute("DELETE FROM coverage WHERE date='{0}'".format(str(datetime.now().date())))
    c.executemany(
        "INSERT INTO allrequirements (id, uniqueid, name, status, rel, upstream, downstream) VALUES (%s,%s,%s,%s,%s,%s,%s)",
        requirements["raw"])
    mydb.commit()
    c.execute(
        "INSERT INTO requirements (rel, status, count, date) SELECT rel, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM allrequirements cross join (select @rn := 0) const GROUP BY rel, status".format(
            str(datetime.now().date())))
    for cov in requirements["coverage"]:
        val = (cov, requirements["coverage"][cov]["covered"], requirements["coverage"][cov]["expected"], str(datetime.now().date()))
        c.execute("INSERT INTO coverage (team, covered, expected, date) VALUES (%s,%s,%s,%s)", val)
    mydb.commit()
    for req in requirements["missingTC"]:
        verify = ""
        missing = ""
        for team in requirements["missingTC"][req]["teams"]:
            verify += team + ", "
            if requirements["missingTC"][req]["teams"][team] == 0:
                missing += team + ", "

        val = (requirements["missingTC"][req]["id"], req, requirements["missingTC"][req]["name"],
         requirements["missingTC"][req]["status"], requirements["missingTC"][req]["rel"], verify[:-2],
         missing[:-2])
        c.execute("INSERT INTO allcoverage (id, uniqueid, name, status, rel, verify, missing) VALUES (%s,%s,%s,%s,%s,%s,%s)",val)
        val = (verify[:-2], requirements["missingTC"][req]["id"])
        c.execute("UPDATE allrequirements SET team = %s WHERE id = %s",val)
    mydb.commit()


def getDesignspecs():
    """Get data for design specifictions"""
    logData("Processing design specifications for %s" % project_name)
    if "designspec" in tables:
        temp = rest_api.getChanges(project["id"], getType("fspec"))
        if len(temp) != 0:
            storeDesignspec(temp, "change")
        else:
            logData("No changes for design specifications")
            storeDesignspec(temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project["id"], getType("fspec"))
        if len(temp) != 0:
            storeDesignspec(temp, "new")


def storeDesignspec(data, type):
    """Store design specficitions"""
    logData("Storing design specifictions for %s" % project_name)
    c = mydb.cursor()
    c.execute("CREATE TABLE IF NOT EXISTS designspec (rel TEXT, team TEXT, status TEXT, count INT, date text)")
    c.execute(
        "CREATE TABLE IF NOT EXISTS alldesignspec (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, team TEXT)")
    c.execute("DELETE FROM designspec WHERE date='{0}'".format(str(datetime.now().date())))
    for designspec in data:
        rel = getrel(designspec)
        team = getTeam(designspec)
        status = getText(designspec["fields"]["status"]).title()
        if type == "change":
            c.execute("DELETE FROM alldesignspec WHERE id=%d" % designspec["id"])
        val = (designspec["id"], designspec["documentKey"], designspec["fields"]["name"], status, rel, team)
        c.execute("INSERT INTO alldesignspec (id, uniqueid, name, status, rel, team) VALUES (%s, %s, %s, %s, %s, %s)",val)
    c.execute(
        "INSERT INTO designspec (rel, team, status, count, date) SELECT rel, team, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM alldesignspec cross join (select @rn := 0) const "
        "GROUP BY rel, team, status".format(str(datetime.now().date())))
    mydb.commit()
    logData("Done storing design specifictions for %s" % project_name)


if __name__ == '__main__':
    checkFolders()
    try:
        logData("Script initiated")
        start_time = timeit.default_timer()  # used for calculating the runtime
        # Calling common non-api class
        helper_functions = common_functions()
        # Calling common api class
        rest_api = api_calls(base_url, username, password)
        # Finding project IDs
        all_projects = getProjects()
        logData("Total projects fetched: %s" % len(all_projects))
        # Identifying feature item type
        item_types = rest_api.getItemTypes()
        # Used for saving custom fields in REST in order to reduce the REST calls
        customtext = {}
        # All teams in Jama
        allTeams = {"SW Engineering": ["SW Engineering", "ESW CC&O Bluecore", "ESW CC&O DECT", "ESW Mobile",
                                       "IOS SW Engineering", "Android SW Engineering", "PC SW Engineering"],
                    "Embedded SW Engineering": ["Embedded SW Engineering"],
                    "HW Engineering": ["HW Engineering", "HW PCB", "HW RF", ],
                    "Manufacturing Test": ["Manufacturing Test"],
                    "Mechanical Engineering": ["Mechanical Engineering", "Mechanical Tests"], "QA": ["QA"],
                    "Regulatory Compliance": ["Regulatory Compliance"],
                    "Packaging & Graphics": ["Packaging & Graphics"],
                    "UX": ["UX"], "DSP Engineering": ["DSP"], "Audio Engineering": ["Audio Engineering"],
                    "TA / Certification": ["TA / Certification"],
                    "Acoustical Engineering": ["Acoustics", "Arcoustics"], "PM": ["PM"], "PMM": ["PMM"],
                    "Industrial Design": ["Industrial Design"], "Strategic Alliances": ["Strategic Alliances"]}

        mydb = mysql.connector.connect(
            host="localhost",
            user="root",
            passwd="jabra2020",
            database="projects"
        )

        c = mydb.cursor()

        all_databases = []
        c.execute("SHOW DATABASES")
        for project in c:
            all_databases.append(project[0])

        alt = list()
        c.execute("SELECT * FROM projects WHERE status = 'Active' AND jama != 0")
        myresult = c.fetchall()
        for project in myresult:
            if str(project[3]) not in all_databases:
                c.execute("CREATE DATABASE `%d`" % project[3])
                print("project ",project[3], " created.")
            alt.append(project[3])
        c.close()
        mydb.commit()
        mydb.close()

        # Go all projects one by one
        for project in all_projects:
            if project["id"] in alt:
                # Timer for each project
                start = timeit.default_timer()
                # Used for saving the current sequence in relations
                sequence = {}
                # Keep only name and remove other form for symbols
                project_name = re.sub('[^\w\-_\. ]', "", project["name"].rstrip())
                # Avoid sending a REST request every time to find out what the numbers mean
                if not project["status"] in customtext:
                    customtext[project["status"]] = {"name": rest_api.getStatus(project["status"])}
                status = customtext[project["status"]]["name"]
                # Only process active projects
                if status == "Active":
                    if any(re.findall(r'ignore|test|demo|template|archive', project_name.lower(), re.IGNORECASE)):
                        continue
                else:
                    continue
                logData("--------------------------------------------------")
                logData("Started fetching data for %s" % project_name)
                logData("--------------------------------------------------")

                # connect to MySQL
                mydb = mysql.connector.connect(
                    host="localhost",
                    user="root",
                    passwd="jabra2020",
                    database=str(project["id"])
                )

                c = mydb.cursor()

                # Get all existing tables in the database
                c.execute("SHOW TABLES")
                tables = [row[0] for row in c]
                """
                Process test plans + test approval status + testmaturity
                """
                testapproval = []
                alltestplans = {}
                testdata = {}
                existing_testplans = getTestPlans(project["id"])
                getTests()
                if testdata:
                    logData("Storing test cases for %s" % project_name)
                    storeTests()
                if testapproval:
                    logData("Storing test case approval status for %s" % project_name)
                    storeTestapproval()
                logData("Done processing test data for %s" % project_name)
                """
                End test plans + test approval + testmaturity

                Process features
                """
                getFeatures()
                """
                End features

                Process Change Requests
                """
                getChangeRequests()
                """
                End Change Requests

                Process design specifications
                """
                getDesignspecs()
                """
                End design specifications

                Process User stories
                """
                getUserstories()
                """
                End User stories

                Process requirements + total coverage
                """
                requirements = {"raw": [], "overall": {}, "coverage": {}, "missingTC": {}}
                getRequirements()
                if requirements["raw"]:
                    logData("Storing requirements for %s" % project_name)
                    storeRequirements()

                """
                End requirements + total coverage

                Process test of features and requirements 
                """
                TestOfFeaturesAndRequirements()

                """
                End test of features and requirements

                Process defects
                """
                getDefects()
                logData("--------------------------------------------------")
                """
                End defects
                """
                mydb.close()# Close database connection
                execution_time = timeit.default_timer() - start
                hour = execution_time // 3600
                execution_time %= 3600
                minutes = execution_time // 60
                logData("Done fetching data for %s" % project_name + " in %i" % hour + " hours and %i" % minutes + " minutes")
        elapsed = timeit.default_timer() - start_time
        hour = elapsed // 3600
        elapsed %= 3600
        minutes = elapsed // 60
        logData("Done processing Jama scripts. Elapsed time: %i" % hour + " hours and %i" % minutes + " minutes")
    except Exception as e:
        logger.exception("main crashed. Error: %s", e)