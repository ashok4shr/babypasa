import urllib.request
import json
from urllib.error import HTTPError

url = 'https://portal-api.upaya.com.np/api/v1/client/add-order'
data = {
    "orders": [{
        "receiver_name": "Ashok Testing",
        "receiver_contact": "+9779813394984",
        "receiver_alternate_number": "",
        "area_id": 6912,
        "product_price": 1200,
        "cod_amount": 1300,
        "remarks": "",
        "receiver_address": "Kalanki Testing",
        "receiver_landmark": "",
        "order_reference_id": "88",
        "weight": 1.0,
        "service_type_id": 3,
        "product_description": "Baby Nest Mosquito Net",
        "length": 0.1,
        "breadth": 0.1,
        "height": 0.1,
        "product_category_id": 5,
        "order_type": "delivery_order",
        "client_note": ""
    }]
}

req = urllib.request.Request(
    url,
    data=json.dumps(data).encode('utf-8'),
    headers={
        'X-API-Key': 'LH9ggVzmn3IoLggcZ79hTzCw3zUelryt',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'User-Agent': 'WordPress/6.5;'
    },
    method='POST'
)

try:
    with urllib.request.urlopen(req) as response:
        resp_data = json.loads(response.read().decode('utf-8'))
        print("Success!", resp_data)
except HTTPError as e:
    print(f"HTTP Error {e.code}: {e.reason}")
    print("Response body:", e.read().decode('utf-8'))
except Exception as e:
    print("Other Error:", e)
