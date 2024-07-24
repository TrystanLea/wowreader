import requests
import json
import math
import datetime
import sys

# 1) Download 1 year of hourly outside temperature data from an emoncms.org feed
feedid = 458775
#feedid = 468108

# False if no API key is required
apikey = False

def get_design_temperature(feedid, start, end, apikey):
    # request url
    params = {'id':feedid, 'start':start, 'end':end, 'skipmissing':0, 'limitinterval':0, 'average':1, 'delta':0, 'interval':3600}
    if apikey: params['apikey'] = apikey

    r = requests.get('https://emoncms.org/feed/data.json', params=params)
    data = json.loads(r.text)

    temperature_histogram = {}
    total_hours = 0

    # For each datapoint 
    for i in range(len(data)):
        temperature = data[i][1]
        # if null
        if temperature is None:
            continue

        # 0.1C bucket size
        bucket = math.floor(temperature*10)/10

        # Allocate to histogram
        if not bucket in temperature_histogram:
            temperature_histogram[bucket] = 0
        temperature_histogram[bucket] += 1
        # Count total hours
        total_hours += 1

    # Sort by temperature ascending
    temperature_histogram = dict(sorted(temperature_histogram.items()))

    prc_996 = None
    prc_990 = None

    # Calculate and display percentage of hours above temperature
    sum_hours = 0
    for temperature, hours in temperature_histogram.items():
        
        sum_hours += hours
        prc = 100 * (1.0 - (sum_hours / total_hours))
        # format to 1 dp as a string

        if prc_996 is None and prc<=99.6:
            prc_996 = temperature
            print ("99.6% of hours above: "+str(prc_996)+"C")

        if prc_990 is None and prc<=99.0:
            prc_990 = temperature
            print ("99.0% of hours above: "+str(prc_990)+"C")

        if prc>98.0:
            prc = "{:.2f}".format(prc)
            #print ("Temperature: "+str(temperature)+"C, Hours: "+str(hours)+", % hours above: "+prc+"%")
        
    print ("Total hours: "+str(total_hours))


# ----------------- Main -----------------

# End time is start of today
# end = datetime.datetime.now()
# end = end.replace(hour=0, minute=0, second=0, microsecond=0)
# end = int(end.timestamp())
 
# end = end - 365*24*3600*3

# Start time is 1 year ago
# start = end - 365*24*3600*1

# Get feed meta data
params ={'id':feedid}
if apikey: params['apikey'] = apikey
r = requests.get('https://emoncms.org/feed/getmeta.json', params=params)
data = json.loads(r.text)
print(r.text)

# print date of start_time
print ("Feed ID:\t"+str(feedid))
print ("Start date:\t"+datetime.datetime.utcfromtimestamp(data['start_time']).strftime('%Y-%m-%d %H:%M:%S'))
print ("End date:\t"+datetime.datetime.utcfromtimestamp(data['end_time']).strftime('%Y-%m-%d %H:%M:%S'))

# Split into years
start = data['start_time']
end = data['end_time']

get_design_temperature(feedid,start,end,apikey)
