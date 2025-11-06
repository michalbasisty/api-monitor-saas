import redis
import json
from datetime import datetime, timezone
import time

# Connect to Redis
r = redis.Redis(host='localhost', port=6379, decode_responses=True)

def publish_metric(endpoint_id, response_time, status_code):
    data = {
        "endpoint_id": endpoint_id,
        "response_time": str(response_time),
        "status_code": str(status_code),
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "method": "GET",
        "path": "/api/test"
    }
    
    # Add to stream
    msg_id = r.xadd('api-metrics', data)
    print(f"Published message {msg_id}")
    return msg_id

# Publish a few normal messages
for i in range(3):
    publish_metric(f"test-endpoint-{i}", 100 + i * 10, 200)
    time.sleep(0.5)

# Create a stuck pending entry by publishing and then killing consumer
stuck_id = publish_metric("stuck-endpoint", 500, 503)
print(f"\nCreated stuck message {stuck_id}")
print("Waiting 10 seconds for message to be picked up and become pending...")
time.sleep(10)

# Show pending entries
pending = r.xpending('api-metrics', 'analytics-group')
print("\nPending entries summary:")
print(json.dumps(pending, indent=2))

# Wait for reclaimer to process
print("\nWaiting 30 seconds for reclaimer to process stuck entry...")
time.sleep(30)

# Check if entry was processed
pending = r.xpending('api-metrics', 'analytics-group')
print("\nPending entries after reclaim:")
print(json.dumps(pending, indent=2))