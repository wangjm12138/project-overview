""" Common REST API functions for JAMA

Description: This file includes common REST API functions for JAMA

Author:         tcydik
Created:        15-02-2016
Updated:
Updated by:
Copyright:      (c) Jabra 2016

Update Notes:

-------------------------------------------------------------------------------
"""

import requests
import json
from datetime import datetime, timedelta


class Requests:

    def get(self,url,auth=None):
        retry = 0
        response = None
        while retry < 5:
            try:
                response = requests.get(url, auth=auth)
            except Exception as e:
                retry = retry + 1
        return response

class api_calls:
    """ Common class for api calls """

    def __init__(self,base_url,username,password):
        """ Initialization method """
        self.base_url = base_url
        self.username = username
        self.password = password
        self.requests = Requests()

    def getFilterId(self,project_id):
        """ Function used to get filter ID """
        print ("-"*50)
        print ("Processing filter ID request...")
        filter_id = None
        existing_filters = []
        resources = "filters"
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            project = "project=%s"%project_id
            url = self.base_url + resources + "?"+ project + "&" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for _filter in json_response_data:
                existing_filters.append({"name": str(_filter["name"]), "id":_filter["id"]})
        # Returning project ID based on user input
        for index,_filter in enumerate(existing_filters):
            print ("%d. - %s"%(index,_filter["name"]))
        print ("-"*50)
        filter_number = input("Choose filter index from above (i.e. 0): ")
        for _filter in existing_filters:
            if _filter["name"] == existing_filters[int(filter_number)]["name"]:
                filter_id = _filter["id"]

        return filter_id

    def getDeletedItems(self, project_id, itemID):
        """ Function used to get deleted items by given project id """
        print("-" * 50)
        print("Processing deleted items...")
        resources = "activities?project=" + str(project_id) + "&eventType=DELETE&objectType=ITEM&itemType=" + str(itemID) + "&deleteEvents=true"
        results = []
        allowed_results = 50
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "&" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for output_results in json_response_data:
                results.append(output_results)

        return results

    def getFilterResults(self,filter_id):
        """ Function used to get filter output """
        print ("-"*50)
        print ("Processing filter result request...")
        filter_results = []
        resources = "filters/%s/results"%filter_id
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "?"+ start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for output_results in json_response_data:
                filter_results.append(output_results)

        return filter_results

    def getItemsByID(self,item_id):
        """ Function used to get item by given ID """
        print ("-"*50)
        print ("Processing abstract item by ID search request...")
        resources = "abstractitems/%s"%item_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        json_response_data = json_response["data"]

        return json_response_data

    def getTestPlanInfo(self,item_id):
        """ Function used to get item by given ID """
        print ("-"*50)
        print ("Processing abstract item by ID search request...")
        resources = "abstractitems/%s"%item_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        json_response_data = json_response["data"]
        return json_response_data

    def getItemsAbstract(self,project_id,item_type_id):
        """ Function used to get items based on abstract information
            Takes project ID and item type ID only
        """
        print ("-"*50)
        print ("Processing abstract item search request...")
        all_items = []
        resources = "abstractitems"
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            project = "project=%s"%project_id
            item_type = "itemType=%s"%item_type_id
            url = self.base_url + resources + "?" + project + "&" + item_type + "&" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for item in json_response_data:
                all_items.append(item)

        return all_items

    def getChanges(self, project_id,item_type_id):
        """ Function used to get items based on abstract information
            Takes project ID and item type ID only and gets only changes
        """
        print ("-"*50)
        print ("Processing abstract item search request...")
        all_items = []
        resources = "abstractitems"
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            project = "project=%s"%project_id
            item_type = "itemType=%s"%item_type_id
            url = self.base_url + resources + "?" + project + "&" + item_type + "&lastActivityDate=" + str(datetime.now().date()-timedelta(30)) + "T00%3A00%3A00%2B00%3A00&" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for item in json_response_data:
                all_items.append(item)

        return all_items

    def getItemTypes(self):
        """ Function used to get all item types
            Returns only ID, display name and type key for each item type
        """
        print ("-"*50)
        print ("Processing item types request...")
        all_types = []
        resources = "itemtypes"
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "?" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            # Saving only ID, display name and type key
            for item_type in json_response_data:
                all_types.append({"id":item_type["id"],"name":item_type["display"],"type_key":item_type["typeKey"]})

        return all_types

    def getProjectId(self):
        """ Function used to get project ID """
        print ("-"*50)
        print ("Processing project ID request...")
        project_id = None
        existing_projects = []
        resources = "projects"
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "?" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for project in json_response_data:
                existing_projects.append({"name": str(project["fields"]["name"]), "id":project["id"]})
        # Returning project ID based on user input
        for index,project in enumerate(existing_projects):
            print ("%d. - %s"%(index,project["name"]))
        print ("-"*50)
        project_number = input("Choose project index from above (i.e. 0): ")
        for project in existing_projects:
            if project["name"] == existing_projects[int(project_number)]["name"]:
                project_id = project["id"]

        return project_id

    def getTestCases(self,test_plan_id,test_group_id):
        """ Function to get test case info for a test group in a test plan """
        test_cases = {}
        resources = "testplans/%s/testgroups/%s/testcases"%(test_plan_id,test_group_id)
        allowed_results = 50
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "?" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for test_case in json_response_data:
                test_cases[test_case["id"]] = test_case

        return test_cases

    def getTestCycles(self,test_plan_id):
        """ Function used to get test cycles for a test plan """
        print ("Processing test cycle request...")
        test_cycles = {}
        resources = "testplans/%s/testcycles"%test_plan_id
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "?" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for test_cycle in json_response_data:
                test_cycles[test_cycle["id"]] = test_cycle

        return test_cycles

    def getTestGroups(self,test_plan_id):
        """ Function used to get test groups for specific test plan """
        test_groups = {}
        resources = "testplans/%s/testgroups"%test_plan_id
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "?" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for test_group in json_response_data:
                test_groups[test_group["id"]] = test_group

        return test_groups

    def getTestPlanId(self,project_id):
        """ Function used to get testplan ID """
        print ("-"*50)
        print ("Processing test plan ID request...")
        test_plan_id = None
        existing_test_plans = []
        resources = "testplans?project=%s"%project_id
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "&" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for test_plan in json_response_data:
                existing_test_plans.append({"name": str(test_plan["fields"]["name"]), "id": test_plan["id"]})
        # Returning test plan ID based on user input
        for index,test_plan in enumerate(existing_test_plans):
            print ("%d. - %s"%(index,test_plan["name"]))
        print ("-"*50)
        test_plan_number = input("Choose test plan index from above: ")
        for test_plan in existing_test_plans:
            if test_plan["name"] == existing_test_plans[int(test_plan_number)]["name"]:
                test_plan_id = test_plan["id"]

        return test_plan_id

    def getTestRun(self,test_run_id):
        """ Function used to get test run information for a given ID """
        resources = "testruns/%s"%test_run_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        json_response_data = json_response["data"]

        return json_response_data

    '''
    def getTestRuns(self,test_cycle_id):
        """ Function used to get test runs for a test cycle """
        test_runs = []
        resources = "testcycles/%s/testruns"%test_cycle_id
        allowed_results = 10
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "?" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for test_run in json_response_data:
                test_runs.append(test_run)

        return test_runs
    '''

    def getAllTestRuns(self,project_id):
        """ Function used to get filter output """
        print ("-"*50)
        print ("Processing filter result request...")
        filter_results = []
        resources = "filters/47797/results?project=%s"%project_id
        allowed_results = 50
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "&"+ start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            try:
                page_info = json_response["meta"]["pageInfo"]
                start_index = page_info["startIndex"] + allowed_results
                result_count = page_info["resultCount"]
                # Processing data side of response
                json_response_data = json_response["data"]
                for output_results in json_response_data:
                    filter_results.append(output_results)
            except KeyError:
                break
        return filter_results

    def getTestRunsByTestplan(self,testplan):
        """ Function used to get test runs for a testplan """
        test_runs = []
        resources = "testruns?testPlan=%s"%testplan
        allowed_results = 50
        max_results = "maxResults=" + str(allowed_results)
        result_count = -1
        start_index = 0
        while result_count != 0:
            start_at = "startAt=" + str(start_index)
            url = self.base_url + resources + "&" + start_at + "&" + max_results
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing meta side of response
            page_info = json_response["meta"]["pageInfo"]
            start_index = page_info["startIndex"] + allowed_results
            result_count = page_info["resultCount"]
            # Processing data side of response
            json_response_data = json_response["data"]
            for test_run in json_response_data:
                test_runs.append(test_run)

        return test_runs

    def getTestRuns(self,testplan,testcase):
        """ Function used to get test run information for a given ID """
        resources = "testruns?testCase={0}&testPlan={1}&sortBy=executionDate.desc&maxResults=1".format(testcase, testplan)
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        json_response_data = json_response["data"]
        return json_response_data

    def getDownStream(self,item_id):
        """ Function used to get downstreams by given ID """
        resources = "items/%s/downstreamrelated"%item_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        try:
            json_response_data = json_response["data"]
        except KeyError:
            json_response_data = []
        return json_response_data

    def getDownstreamRelationships(self, item_id):
        """ Function used to get downstreams by given ID """
        resources = "items/%s/downstreamrelationships" % item_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        try:
            json_response_data = json_response["data"]
        except KeyError:
            json_response_data = []
        return json_response_data

    def getUpstreamRelationships(self, item_id):
        """ Function used to get upstreams by given ID """
        resources = "items/%s/upstreamrelationships" % item_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        try:
            json_response_data = json_response["data"]
        except KeyError:
            json_response_data = []
        return json_response_data

    def getParent(self,item_id):
        resources = "items/%s/parent"%item_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        try:
            json_response_data = json_response["data"]
        except Exception:
            json_response_data = "Unspecified"
        return json_response_data

    def getStatus(self,status_id):
        """ Function used to get status by given ID
            Returns only the name for status
        """
        resources = "picklistoptions/%s"%status_id
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        json_response_data = json_response["data"]
        return json_response_data["name"]

    def getTeam(self,verifyids):
        """ Function used to get status by given ID
            Returns only the name for status
        """
        verifyingTeams = {}
        for verifyid in verifyids:
            resources = "picklistoptions/%s"%verifyid
            url = self.base_url + resources
            response = requests.get(url, auth=(self.username, self.password))
            json_response = json.loads(response.text)
            # Processing data side of response
            json_response_data = json_response["data"]
            verifyingTeams[json_response_data["name"]] = 0
        return verifyingTeams


    def getRelease(self,releaseid):
        """ Function used to get status by given ID
            Returns only the name for status
        """
        resources = "releases/%s"%releaseid
        url = self.base_url + resources
        response = requests.get(url, auth=(self.username, self.password))
        json_response = json.loads(response.text)
        # Processing data side of response
        try:
            json_response_data = json_response["data"]
        except Exception:
            return "Unspecified"
        return json_response_data["name"]

    def updateItem(self,item_id,payload,verbose=False):
        """ Function used to edit item information
            Returns status code to confirm succesfull update
        """
        # payload = all fields + updated field
        resources = "items/%s"%item_id
        url = self.base_url + resources
        response = requests.put(url, json=payload,auth=(self.username, self.password))
        if verbose:
            print (response.text)

        return response.status_code

    def updateTestRun(self,test_run_id,payload,verbose=False):
        """ Function used to edit test run information
            Note that not all fields can be updated which will result with 'Bad Request'
        """
        # payload = all editable fields + updated fields
        resources = "testruns/%s"%test_run_id
        url = self.base_url + resources
        response = requests.put(url, json=payload,auth=(self.username, self.password))
        if verbose:
            print (response.text)

        return response.status_code
