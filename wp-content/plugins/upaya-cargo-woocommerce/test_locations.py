import urllib.request
import json

url = 'https://portal-api.upaya.com.np/api/v1/client/locations'
req = urllib.request.Request(
    url,
    headers={
        'X-API-Key': 'LH9ggVzmn3IoLggcZ79hTzCw3zUelryt',
        'Accept': 'application/json',
        'User-Agent': 'WordPress/6.5;'
    }
)

try:
    with urllib.request.urlopen(req) as response:
        resp_data = json.loads(response.read().decode('utf-8'))
        found = []
        if 'data' in resp_data:
            for city in resp_data['data']:
                if city.get('id') == 3271:
                    print("Found city 3271:", city['name'])
                if 'areas' in city:
                    for area in city['areas']:
                        if area.get('locationId') == 792:
                            print(f"For locationId 792, the area id is {area.get('id')} and area name is {area.get('name')}")
except Exception as e:
    print("Error:", e)
