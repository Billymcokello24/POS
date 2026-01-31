# POS Realtime Bridge

This is a small Redis -> Socket.IO bridge used to deliver business SSE events via WebSocket (Socket.IO) instead of long-lived PHP SSE.

Prerequisites:
- Node 18+ (or compatible)
- Redis accessible (default redis://127.0.0.1:6379)

Install and run:

```bash
cd tools/realtime-server
npm install
PORT=4000 REDIS_URL=redis://127.0.0.1:6379 npm start
```

Client usage (browser):

```js
import { io } from 'socket.io-client'
const socket = io('http://localhost:4000')
// after auth, join the business room
socket.emit('subscribe:business', BUSINESS_ID)
socket.on('business:update', (payload) => console.log('update', payload))
```

This bridge subscribes to Redis pubsub channel `business_sse_channel:{businessId}` and forwards published events to clients in the room `business_{businessId}`.

