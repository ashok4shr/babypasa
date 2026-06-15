import json
with open('out.json', 'r', encoding='utf-8') as f:
    data = json.load(f)
    print("Type of data:", type(data))
    if isinstance(data, dict):
        print("Keys:", data.keys())
        if 'data' in data:
            print("Type of data['data']:", type(data['data']))
            if isinstance(data['data'], list) and len(data['data']) > 0:
                print("Keys of first item in data['data']:", data['data'][0].keys())
                print("First item sample:", data['data'][0])
