import http from 'http'

import { createClient } from 'redis'
import { Server } from 'socket.io'

const PORT = process.env.PORT ? Number(process.env.PORT) : 4000
const REDIS_URL = process.env.REDIS_URL || 'redis://127.0.0.1:6379'

async function start() {
  const server = http.createServer()
  const io = new Server(server, {
    cors: {
      origin: '*',
      methods: ['GET', 'POST']
    }
  })

  // Redis client for pubsub
  const sub = createClient({ url: REDIS_URL })
  sub.on('error', (err) => console.error('Redis subscriber error', err))
  await sub.connect()

  io.on('connection', (socket) => {
    console.log('client connected', socket.id)

    socket.on('subscribe:business', (businessId) => {
      try {
        const room = `business_${businessId}`
        socket.join(room)
        console.log(`socket ${socket.id} joined ${room}`)
      } catch (e) {
        console.error('subscribe error', e)
      }
    })

    socket.on('unsubscribe:business', (businessId) => {
      try {
        const room = `business_${businessId}`
        socket.leave(room)
        console.log(`socket ${socket.id} left ${room}`)
      } catch (e) {
        console.error('unsubscribe error', e)
      }
    })

    socket.on('disconnect', () => {
      console.log('client disconnected', socket.id)
    })
  })

  // Subscribe to pattern and forward messages into rooms
  await sub.pSubscribe('business_sse_channel:*', (message, channel) => {
    try {
      const payload = JSON.parse(message)
      const parts = channel.split(':')
      const businessId = parts.slice(1).join(':')
      const room = `business_${businessId}`
      io.to(room).emit('business:update', payload)
    } catch (e) {
      console.error('Failed to forward message', e)
    }
  })

  server.listen(PORT, () => {
    console.log(`Realtime bridge listening on :${PORT}, Redis ${REDIS_URL}`)
  })
}

start().catch((err) => {
  console.error('Bridge failed', err)
  process.exit(1)
})
