import os
import sys
import time
import mysql.connector
from api.api_common import api_calls 
from helper.common import common_functions
from datetime import datetime
import logging

os.chdir(os.path.dirname(os.path.abspath(sys.argv[0])))

#Used for logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
handler = logging.FileHandler("logs/" + datetime.now().strftime('log_%d_%m_%Y.log'))
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

# Jama details
base_url = "https://jabra.jamacloud.com/rest/v1/"
# Read only user
username = "RESTuser"
password = "Qetuo13579"

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

def getRel(item):
    """Get release name for an item"""
    if "rel" in item["fields"]:
        relid = item["fields"]["rel"]
        if not relid in customtext:
            customtext[relid] = {"name": rest_api.getRel(relid)}
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

def getFeatures(project):
    """Get data for features"""
    if "features" in tables:
        temp = rest_api.getChanges(project, getType("feat"))
        if len(temp) != 0:
            storeFeatures(project, temp, "change")
        else:
            logData("No changes for features")
            storeFeatures(project, temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project, getType("feat"))
        if len(temp) != 0:
            storeFeatures(project, temp, "new")

def storeFeatures(project,data,type):
    """Store features"""
    logData("Storing features for %s" % project)
    c = mydb.cursor(buffered=True)
    c.execute("CREATE TABLE IF NOT EXISTS features (rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS allfeatures (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT)")
    c.execute("DELETE FROM features WHERE date='{0}'".format(str(datetime.now().date())))
    for feat in data:
        if type == "change":
            c.execute("DELETE FROM allfeatures WHERE id=%d" % feat["id"])
        val = (feat["id"], feat["documentKey"], feat["fields"]["name"], getText(feat["fields"]["status"]).title(), getRel(feat))
        c.execute("INSERT INTO allfeatures (id, uniqueid, name, status, rel) VALUES (%s, %s, %s, %s, %s)", val)
    c.execute(
        "INSERT INTO features (rel, status, count, date) SELECT rel, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM allfeatures cross join (select @rn := 0) const GROUP BY rel, status".format(
            str(datetime.now().date())))
    mydb.commit()
    logData("Done storing features for %s" % project)

def getChangeRequests(project):
    """Get data for change requests"""
    if "changes" in tables:
        temp = rest_api.getChanges(project, getType("cr"))
        if len(temp) != 0:
            storeChangeRequests(project, temp, "change")
        else:
            logData("No changes for change requests")
            storeChangeRequests(project, temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project, getType("cr"))
        if len(temp) != 0:
            storeChangeRequests(project, temp, "change")

def storeChangeRequests(project,data,type):
    """Store change requests"""
    logData("Storing change requests for %s" % project)
    c = mydb.cursor(buffered=True)
    c.execute("CREATE TABLE IF NOT EXISTS changes (rel TEXT, status TEXT, priority TEXT, requester TEXT, date TEXT, count INT)")
    c.execute("CREATE TABLE IF NOT EXISTS allchanges (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, priority TEXT, requester TEXT)")
    c.execute("DELETE FROM changes WHERE date='{0}'".format(str(datetime.now().date())))
    for change in data:
        status = getText(change["fields"]["status"]).title()
        rel = getRel(change)
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
    logData("Done storing change requests for %s" % project)

def getDefects(project):
    """"Get data for defects"""
    if "defects" in tables:
        temp = rest_api.getChanges(project, getType("bug"))
        if len(temp) != 0:
            storeDefects(project, temp, "change")
        else:
            logData("No changes for defects")
            storeDefects(project, temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project, getType("bug"))
        if len(temp) != 0:
            storeDefects(project, temp, "new")

def storeDefects(project,data,type):
    """"Store defects"""
    logData("Storing defects for %s" % project)
    upstream = ""
    c = mydb.cursor(buffered=True)
    c.execute("CREATE TABLE IF NOT EXISTS defects (rel TEXT, team TEXT, status TEXT, priority TEXT, count INT, date TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS alldefects (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, team TEXT, priority TEXT, jira TEXT, upstream TEXT)")
    c.execute("DELETE FROM defects WHERE date='{0}'".format(str(datetime.now().date())))
    for defect in data:
        upstreamrelations = rest_api.getUpstreamRelationships(defect["id"])
        status = getText(defect["fields"]["status"]).title()
        rel = getRel(defect)
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
        val= (defect["id"], defect["documentKey"], defect["fields"]["name"], status, rel, team, priority, jira, upstream[:-2])
        c.execute("INSERT INTO alldefects (id, uniqueid, name, status, rel, team, priority, jira, upstream) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",val)
    c.execute(
        "INSERT INTO defects (rel, team, status, count, date, priority) SELECT rel, team, status, count((@rn := @rn + 1)) as count, '{0}' as date, priority FROM alldefects cross join (select @rn := 0) const "
        "GROUP BY rel, team, status, priority".format(str(datetime.now().date())))
    mydb.commit()
    logData("Done storing defects for %s" % project)

def getUserstories(project):
    """Get data for user stories"""
    if "userstories" in tables:
        temp = rest_api.getChanges(project, getType("sty"))
        if len(temp) != 0:
            storeUserstories(project, temp, "change")
        else:
            storeUserstories(project, temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project, getType("sty"))
        if len(temp) != 0:
            storeUserstories(project, temp, "new")

def storeUserstories(project, data, type):
    """Store users stories"""
    logData("Storing user stories for %s" % project)
    c = mydb.cursor(buffered=True)
    c.execute("CREATE TABLE IF NOT EXISTS userstories (rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS alluserstories (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT)")
    c.execute("DELETE FROM userstories WHERE date='{0}'".format(str(datetime.now().date())))
    for userstory in data:
        try:
            rel = getRel(userstory)
            status = getText(userstory["fields"]["status"]).title()
            if type == "change":
                c.execute("DELETE FROM alluserstories WHERE id=%d" % userstory["id"])
            val=(userstory["id"], userstory["documentKey"], userstory["fields"]["name"], status, rel)
            c.execute("INSERT INTO alluserstories (id, uniqueid, name, status, rel) VALUES (%s, %s, %s, %s, %s)",val)
        except:
            logData("No user stories found for %s" % project)
    mydb.commit()
    c.execute(
        "INSERT INTO userstories (rel, status, count, date) SELECT rel, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM alluserstories cross join (select @rn := 0) const GROUP BY rel, status".format(
            str(datetime.now().date())))
    mydb.commit()
    logData("Done storing user stories for %s" % project)

def getDesignspecs(project):
    """Get data for design specifictions"""
    if "designspec" in tables:
        temp = rest_api.getChanges(project, getType("fspec"))
        if len(temp) != 0:
            storeDesignspec(project, temp, "change")
        else:
            logData("No changes for design specifications")
            storeDesignspec(project, temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project, getType("fspec"))
        if len(temp) != 0:
            storeDesignspec(project, temp, "new")

def storeDesignspec(project, data, type):
    """Store design specficitions"""
    logData("Storing design specifictions for %s" % project)
    c = mydb.cursor(buffered=True)
    c.execute("CREATE TABLE IF NOT EXISTS designspec (rel TEXT, team TEXT, status TEXT, count INT, date text)")
    c.execute("CREATE TABLE IF NOT EXISTS alldesignspec (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, team TEXT)")
    c.execute("DELETE FROM designspec WHERE date='{0}'".format(str(datetime.now().date())))
    for designspec in data:
        rel = getRel(designspec)
        team = getTeam(designspec)
        status = getText(designspec["fields"]["status"]).title()
        if type == "change":
            c.execute("DELETE FROM alldesignspec WHERE id=%d" % designspec["id"])
        val=(designspec["id"], designspec["documentKey"], designspec["fields"]["name"], status, rel, team)
        c.execute("INSERT INTO alldesignspec (id, uniqueid, name, status, rel, team) VALUES (%s, %s, %s, %s, %s, %s)", val)
    c.execute(
        "INSERT INTO designspec (rel, team, status, count, date) SELECT rel, team, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM alldesignspec cross join (select @rn := 0) const "
        "GROUP BY rel, team, status".format(str(datetime.now().date())))
    mydb.commit()
    logData("Done storing design specifictions for %s" % project)

def getRequirements(project):
    """Get data for requirements and test coverage"""
    logData("Processing requirements for %s" % project)
    if "requirements" in tables:
        temp = rest_api.getChanges(project, getType("req"))
        if len(temp) != 0:
            storeRequirements(project, temp, "change")
        else:
            storeRequirements(project, temp, "nochange")
    else:
        temp = rest_api.getItemsAbstract(project, getType("req"))
        if len(temp) != 0:
            storeRequirements(project, temp, "new")

def storeRequirements(project, data, type):
    """Store requirements"""
    logData("Storing requirements for %s" % project)
    coveredTeams = ["Industrial Design", "Strategic Alliances", "PM", "PMM"]  # Teams that don't require testcase
    c = mydb.cursor(buffered=True)
    c.execute("CREATE TABLE IF NOT EXISTS requirements (rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS coverage (team text, covered int, expected int, date text)")
    c.execute("CREATE TABLE IF NOT EXISTS allrequirements (id INT, uniqueid TEXT, name TEXT, status TEXT, rel TEXT, upstream TEXT, downstream TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS allcoverage (id INT, uniqueid TEXT, name INT, status TEXT, rel TEXT, verify TEXT, missing TEXT)")
    c.execute("DELETE FROM requirements WHERE date='{0}'".format(str(datetime.now().date())))
    c.execute("DELETE FROM coverage WHERE date='{0}'".format(str(datetime.now().date())))
    for requirement in data:
        #Get status and release in text format
        status = getText(requirement["fields"]["status"]).title()
        rel = getRel(requirement)
        downstreams = rest_api.getDownStream(requirement["id"])
        downstreamrelations = rest_api.getDownstreamRelationships(requirement["id"])
        upstreamrelations = rest_api.getUpstreamRelationships(requirement["id"])
        fromitem = ""
        toitem = ""
        for n in upstreamrelations:
            fromitem += str(n["fromItem"]) + ", "
        for n in downstreamrelations:
            toitem += str(n["toItem"]) + ", "
        #Check involved teams
        if "verifying_teams_new$89009" in requirement["fields"]:
            teams = rest_api.getTeam(requirement["fields"]["verifying_teams_new$89009"])
            requirement["fields"]["verifying_teams_new$89009"] = teams
            requirements["missingTC"][requirement["fields"]["documentKey"]] = {"id": requirement["id"], "name": requirement["fields"]["name"], "status": status, "rel": rel, "teams": teams}
            #Test coverage requirements
            for team in teams:
                # Default teams that don't require test case
                if team in coveredTeams:
                    requirement["fields"]["verifying_teams_new$89009"][team] = 1

            #Check if the teams have covered
            if len(downstreams) != 0:
                for downstream in downstreams:
                    #Check if the downstream is a test case
                    if 'TC' in downstream["documentKey"]:
                        #Get the team responsible for this test case
                        verifyingteam = getTeam(downstream)
                        # Check if the verifying team has covered
                        for v in teams:
                            for parentTeam, team in allTeams.items():
                                if verifyingteam == parentTeam and v in team and not requirement["fields"]["verifying_teams_new$89009"][v] == 1:
                                    requirement["fields"]["verifying_teams_new$89009"][v] = 1

            #Check TC for missing teams
            elif len(downstreams) != 0 and len(teams) != 0:
                for downstream in downstreams:
                    if 'TC' in downstream["documentKey"]:
                        team = getTeam(downstream)
                        for parent, child in allTeams.items():
                            if team == parent:
                                for v in child:
                                    if v in requirements["missingTC"][requirement["fields"]["documentKey"]]["teams"]:
                                        requirements["missingTC"][requirement["fields"]["documentKey"]]["teams"][v] = 1
        if type == "change":
            c.execute("DELETE FROM allrequirements WHERE id=%d" % requirement["id"])
            c.execute("DELETE FROM allcoverage WHERE id=%d" % requirement["id"])
        #Save the requirement in raw format
        val=(requirement["id"], requirement["documentKey"], requirement["fields"]["name"], status, rel, fromitem[:-2], toitem[:-2])
        c.execute("INSERT INTO allrequirements (id, uniqueid, name, status, rel, upstream, downstream) "
                  "VALUES (%s,%s,%s,%s,%s,%s,%s)", val)
    c.execute(
        "INSERT INTO requirements (rel, status, count, date) SELECT rel, status, count((@rn := @rn + 1)) as count, '{0}' as date FROM allrequirements cross join (select @rn := 0) const GROUP BY rel, status".format(
            str(datetime.now().date())))

    #Insert coverage data
    for req in requirements["missingTC"]:
        verify = ""
        missing = ""
        for team in requirements["missingTC"][req]["teams"]:
            verify += team+", "
            if requirements["missingTC"][req]["teams"][team] == 0:
                missing += team+", "
        val=(requirements["missingTC"][req]["id"], req, requirements["missingTC"][req]["name"],
                                             requirements["missingTC"][req]["status"], requirements["missingTC"][req]["rel"], verify[:-2], missing[:-2])
        c.execute("INSERT INTO allcoverage (id, uniqueid, name, status, rel, verify, missing) "
                  "VALUES (%s,%s,%s,%s,%s,%s,%s)", val)

    #Get all involved teams from the database
    teams = {}
    temp = c.execute("SELECT verify, missing FROM allcoverage")
    if temp:
        for row in temp:
            verify = row[0]
            missing = row[1]
            if verify: #Check if data exists
                if "," in verify:
                    team = verify.split(", ") #Save to array
                    for t in team:
                        if not t in teams:
                            teams[t] = {"expected": 1, "missing": 0}
                        else:
                            teams[t]["expected"] += 1
                else:
                    if not verify in teams:
                        teams[verify] = {"expected": 1, "missing": 0}
                    else:
                        teams[verify]["expected"] += 1
            if missing:
                if "," in missing:
                    team = missing.split(", ")
                    for t in team:
                        teams[t]["missing"] += 1
                else:
                    teams[missing]["missing"] += 1

    #Insert into database
    for team in teams:
        val=(team, teams[team]["expected"]-teams[team]["missing"], teams[team]["expected"], str(datetime.now().date()))
        c.execute("INSERT INTO coverage (team, covered, expected, date) VALUES (%s,%s,%s,%s)", val)
    mydb.commit()
    logData("Done storing requirements for %s" % project)

def getTests(testplan):
    """Get data for testcases"""
    testplan_info = rest_api.getTestPlanInfo(testplan)
    testdata[testplan] = {"name": testplan_info["fields"]["name"], "archived": testplan_info["archived"], "testgroup": {}, "testcycles": rest_api.getTestCycles(testplan), "overall": {}}
    # Finding test groups in a test plan
    test_groups = rest_api.getTestGroups(testplan)
    # Getting test cases per each test group in a test plan
    for test_group in test_groups:
        testdata[testplan]["testgroup"][test_group] = {"id": test_group, "name": test_groups[test_group]["name"]}
        testdata[testplan]["testgroup"][test_group]["testcases"] = rest_api.getTestCases(testplan, test_group)
    # Get latest test run data
    logData("Getting test runs for %s" %testdata[testplan]["name"])
    getTestRuns(testplan)
    # Save and sort testcases by testplans
    for testplan in testdata:
        for testgroup in testdata[testplan]["testgroup"]:
            for testcase in testdata[testplan]["testgroup"][testgroup]["testcases"]:
                case = testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]
                rel = getRel(case)
                upstreamrelations = rest_api.getUpstreamRelationships(testcase)
                #downstreamrelations = rest_api.getDownstreamRelationships(testcase)
                case["upstream"] = ""
                case["downstream"] = ""
                for n in upstreamrelations:
                    case["upstream"] += str(n["fromItem"]) + ", "
                # Avoid saving wrong data. Clean up the executionDates if it matches the following status
                if case["fields"]["testCaseStatus"] != "NOT_RUN" and "executionDate" not in case["fields"]:
                    case["fields"]["testCaseStatus"] = "NOT_SCHEDULED"
                if case["fields"]["testCaseStatus"] == "NOT_RUN" or case["fields"]["testCaseStatus"] == "SCHEDULED" or case["fields"]["testCaseStatus"] == "NOT_SCHEDULED":
                    if "executionDate" in case["fields"]:
                        del case["fields"]["executionDate"]
                if case["fields"]["testCaseStatus"] == "NOT_RUN":
                    case["fields"]["testCaseStatus"] = "SCHEDULED"
                #Build array dict with data
                if not rel in testdata[testplan]["overall"]:
                    testdata[testplan]["overall"][rel] = {}
                if not case["fields"]["testCaseStatus"] in testdata[testplan]["overall"][rel]:
                    testdata[testplan]["overall"][rel][case["fields"]["testCaseStatus"]] = 1
                else:
                    testdata[testplan]["overall"][rel][case["fields"]["testCaseStatus"]] += 1
    logData("Storing test data for %s" %testdata[testplan]["name"])
    storeTests(testplan)

def getTestRuns(testplan):
    """Get data for test maturity"""
    testruns = rest_api.getTestRunsByTestplan(testplan)
    if testruns:
        for testrun in testruns:
            testcase = testrun["fields"]["testCase"]
            testcycle = testrun["fields"]["testCycle"]
            for testgroup in testdata[testplan]["testgroup"]:
                if testcase in testdata[testplan]["testgroup"][testgroup]["testcases"]:
                    if testrun["createdDate"] > testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["createdDate"]:
                        if "executionDate" in testrun["fields"]:
                            if not "executionDate" in testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]:
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["createdDate"] = testrun["createdDate"]
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["executionDate"] = testrun["fields"]["executionDate"]
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["testCaseStatus"] = testrun["fields"]["testRunStatus"]
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["testCycle"] = testcycle
                            elif datetime.strptime(testrun["fields"]["executionDate"], "%Y-%m-%d") > datetime.strptime(testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["executionDate"], "%Y-%m-%d"):
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["createdDate"] = testrun["createdDate"]
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["executionDate"] = testrun["fields"]["executionDate"]
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["testCaseStatus"] = testrun["fields"]["testRunStatus"]
                                testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["testCycle"] = testcycle
                        else:
                            testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["createdDate"] = testrun["createdDate"]
                            testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["testCaseStatus"] = testrun["fields"]["testRunStatus"]
                            testdata[testplan]["testgroup"][testgroup]["testcases"][testcase]["fields"]["testCycle"] = testcycle

def storeTests(testplan):
    """ Store test plans """

    mydb = mysql.connector.connect(
        host="localhost",
        user="root",
        passwd="jabra2020",
        database=str(project)
    )

    c = mydb.cursor(buffered=True)

    c.execute("CREATE TABLE IF NOT EXISTS tests (testplan_id INT, testplan_name TEXT, testplan_status TEXT, rel TEXT, status TEXT, count INT, date TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS testcases (testplan_id INT, testplan_name TEXT, testgroup_id INT, testgroup_name TEXT, testcycle_id INT, testcycle_name TEXT, rel TEXT, id INT, uniqueid TEXT, name TEXT, status TEXT, upstream TEXT, executionDate TEXT)")
    c.execute("DELETE FROM testcases WHERE testplan_id={0}".format(testplan))
    c.execute("DELETE FROM tests WHERE date='{0}' AND testplan_id={1}".format(str(datetime.now().date()), testplan))
    c.execute("SELECT testplan_id FROM tests GROUP BY testplan_id")
    rows = c.fetchall()
    tpsql = [row[0] for row in rows]
    # Check for unarchived test plans, name changes and remove deleted testplans
    if int(testplan) in tpsql:
        c.execute("SELECT testplan_name FROM tests WHERE testplan_id={0} LIMIT 1".format(testplan))
        tmp = c.fetchone()
        if tmp[0] != testdata[testplan]["name"]:
           c.execute("UPDATE tests SET testplan_name='{0}' WHERE testplan_id={1}".format(testdata[testplan]["name"], testplan))
        if testdata[testplan]["archived"]:
            c.execute("UPDATE tests SET testplan_status='Inactive' WHERE testplan_id={0}".format(testplan))
        if not testdata[testplan]["archived"]:
            c.execute("UPDATE tests SET testplan_status='Active' WHERE testplan_id={0}".format(testplan))
    else:
        print("Stop!")
        exit(0)
        c.execute("DELETE FROM tests WHERE testplan_id={0}".format(testplan))
    mydb.commit()
    #Insert test data grouped by testplan, status, count and date
    for testplan in testdata:
        plan = testdata[testplan]
        for rel in plan["overall"]:
            for status in plan["overall"][rel]:
                val=(testplan, plan["name"], rel, status, plan["overall"][rel][status], str(datetime.now().date()))
                c.execute("INSERT INTO tests (testplan_id, testplan_name, testplan_status, rel, status, count, date) VALUES (%s, %s ,'Active', %s, %s, %s, %s)",val)
        #Ungrouped test data
        for testgroup in plan["testgroup"]:
            for test_case in plan["testgroup"][testgroup]["testcases"]:
                path = plan["testgroup"][testgroup]["testcases"][test_case]
                path["fields"]["rel"] = getRel(path)
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
                    val=(testplan, plan["name"], testgroup, plan["testgroup"][testgroup]["name"], testcycle, testcycle_name, path["fields"]["rel"], test_case, path["documentKey"], path["fields"]["name"], path["fields"]["testCaseStatus"], path["upstream"], path["fields"]["executionDate"])
                    c.execute("INSERT INTO testcases (testplan_id, testplan_name, testgroup_id, testgroup_name, testcycle_id, testcycle_name, rel, id, uniqueid, name, status, upstream, executionDate) values (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",val)
                else:
                    val=(testplan, plan["name"], testgroup, plan["testgroup"][testgroup]["name"], testcycle, testcycle_name, path["fields"]["rel"], test_case, path["documentKey"], path["fields"]["name"], path["fields"]["testCaseStatus"], path["upstream"], "0")
                    c.execute("INSERT INTO testcases (testplan_id, testplan_name, testgroup_id, testgroup_name, testcycle_id, testcycle_name, rel, id, uniqueid, name, status, upstream, executionDate) values (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",val)
    mydb.commit()

# Calling common non-api class
helper_functions = common_functions()
# Calling common api class
rest_api = api_calls(base_url, username, password)
# Identifying feature item type
item_types = rest_api.getItemTypes()
# Used for saving custom fields in REST in order to reduce the REST calls
customtext = {}
sequence = {}
# All teams in Jama
allTeams = {"SW Engineering": ["SW Engineering", "ESW CC&O Bluecore", "ESW CC&O DECT", "ESW Mobile",
                               "IOS SW Engineering", "Android SW Engineering", "PC SW Engineering"],
            "Embedded SW Engineering": ["Embedded SW Engineering"],
            "HW Engineering": ["HW Engineering", "HW PCB", "HW RF", ],
            "Manufacturing Test": ["Manufacturing Test"],
            "Mechanical Engineering": ["Mechanical Engineering", "Mechanical Tests"], "QA": ["QA"],
            "Regulatory Compliance": ["Regulatory Compliance"],
            "Packaging & Graphics": ["Packaging & Graphics"],
            "UX": ["UX"], "DSP Engineering": ["DSP"],
            "Audio Engineering": ["Audio Engineering"], "TA / Certification": ["TA / Certification"],
            "Acoustical Engineering": ["Acoustics", "Arcoustics"]}

project = sys.argv[1]
type = sys.argv[2]

pid = str(os.getpid())
pidfile = "instance.pid"

while os.path.isfile(pidfile):
    logData("Another instance is already running. Sleeping for 10 seconds")
    time.sleep(10)

open(pidfile, 'w').write(pid)

# Get all existing tables in the database

mydb = mysql.connector.connect(
    host="localhost",
    user="root",
    passwd="jabra2020",
    database=str(project)
)

c = mydb.cursor(buffered=True)

c.execute("SHOW TABLES")
tables = [row[0] for row in c]

try:
    if type == "defects":
        logData("Fetching defects for %s" %project)
        getDefects(project)
    if type == "features":
        logData("Fetching features for %s" %project)
        getFeatures(project)
    if type == "userstories":
        logData("Fetching user stories for %s" %project)
        getUserstories(project)
    if type == "changes":
        logData("Fetching change requests for %s" %project)
        getChangeRequests(project)
    if type == "designspec":
        logData("Fetching design specifications for %s" %project)
        getDesignspecs(project)
    if type == "requirements":
        requirements = {"missingTC": {}}
        logData("Fetching requirements for %s" %project)
        getRequirements(project)
    if type == "testplan":
        testplan = sys.argv[3]
        testdata = {}
        logData("Fetching test data for testplan %s" %testplan)
        getTests(testplan)
except Exception as e:
    logger.exception("main crashed. Error: %s", e)
    logData("Crashed")
finally:
    os.unlink(pidfile)
    logData("Success")