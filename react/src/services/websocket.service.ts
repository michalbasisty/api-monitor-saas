/**
 * WebSocket Service
 * 
 * Centralized WebSocket management with:
 * - Automatic reconnection
 * - Event subscription/unsubscription
 * - Error handling
 * - Graceful shutdown
 */

export type WebSocketEventType = 'monitoring_result' | 'error' | 'connected' | 'disconnected'
export type WebSocketEventCallback = (data: any) => void

interface WebSocketEvent {
  type: WebSocketEventType
  callback: WebSocketEventCallback
}

class WebSocketService {
  private static instance: WebSocketService
  private ws: WebSocket | null = null
  private url: string = ''
  private listeners: Map<WebSocketEventType, Set<WebSocketEventCallback>> = new Map()
  private reconnectAttempts: number = 0
  private maxReconnectAttempts: number = 5
  private reconnectDelay: number = 1000 // ms
  private messageQueue: any[] = []
  private isIntentionallyClosed: boolean = false
  private heartbeatInterval: NodeJS.Timeout | null = null

  private constructor() {
    this.initializeEventMap()
  }

  static getInstance(): WebSocketService {
    if (!WebSocketService.instance) {
      WebSocketService.instance = new WebSocketService()
    }
    return WebSocketService.instance
  }

  private initializeEventMap(): void {
    const eventTypes: WebSocketEventType[] = ['monitoring_result', 'error', 'connected', 'disconnected']
    eventTypes.forEach(type => {
      this.listeners.set(type, new Set())
    })
  }

  /**
   * Connect to WebSocket server
   * @param url WebSocket URL
   * @throws Error if connection fails after max retry attempts
   */
  async connect(url: string): Promise<void> {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      console.warn('[WebSocket] Already connected')
      return
    }

    this.url = url
    this.isIntentionallyClosed = false

    return new Promise((resolve, reject) => {
      try {
        this.ws = new WebSocket(url)

        this.ws.onopen = () => {
          console.log('[WebSocket] Connected')
          this.reconnectAttempts = 0
          this.startHeartbeat()
          this.flushMessageQueue()
          this.emit('connected', { timestamp: new Date().toISOString() })
          resolve()
        }

        this.ws.onmessage = (event: MessageEvent) => {
          try {
            const data = JSON.parse(event.data)
            this.emit('monitoring_result', data)
          } catch (error) {
            console.error('[WebSocket] Failed to parse message:', error)
            this.emit('error', { message: 'Invalid message format', error })
          }
        }

        this.ws.onerror = (event: Event) => {
          console.error('[WebSocket] Error:', event)
          this.emit('error', { message: 'WebSocket error', event })
          reject(new Error('WebSocket connection failed'))
        }

        this.ws.onclose = () => {
          console.log('[WebSocket] Connection closed')
          this.stopHeartbeat()
          
          if (!this.isIntentionallyClosed) {
            this.attemptReconnect()
          } else {
            this.emit('disconnected', { timestamp: new Date().toISOString() })
          }
        }

        // Connection timeout
        const connectionTimeout = setTimeout(() => {
          if (this.ws?.readyState !== WebSocket.OPEN) {
            this.ws?.close()
            reject(new Error('WebSocket connection timeout'))
          }
        }, 5000)

        if (this.ws.readyState === WebSocket.OPEN) {
          clearTimeout(connectionTimeout)
          resolve()
        }
      } catch (error) {
        reject(error)
      }
    })
  }

  /**
   * Subscribe to WebSocket events
   * @param eventType Event type to listen for
   * @param callback Function to call when event is emitted
   */
  subscribe(eventType: WebSocketEventType, callback: WebSocketEventCallback): () => void {
    const listeners = this.listeners.get(eventType)
    if (!listeners) {
      console.warn(`[WebSocket] Unknown event type: ${eventType}`)
      return () => {}
    }

    listeners.add(callback)

    // Return unsubscribe function
    return () => {
      listeners.delete(callback)
    }
  }

  /**
   * Emit event to all listeners
   * @param eventType Event type
   * @param data Event data
   */
  private emit(eventType: WebSocketEventType, data: any): void {
    const listeners = this.listeners.get(eventType)
    if (!listeners) return

    listeners.forEach(callback => {
      try {
        callback(data)
      } catch (error) {
        console.error(`[WebSocket] Error in listener for ${eventType}:`, error)
      }
    })
  }

  /**
   * Send message through WebSocket
   * @param data Data to send
   */
  send(data: any): void {
    if (!this.ws) {
      console.error('[WebSocket] WebSocket is not initialized')
      return
    }

    const message = typeof data === 'string' ? data : JSON.stringify(data)

    if (this.ws.readyState === WebSocket.OPEN) {
      try {
        this.ws.send(message)
      } catch (error) {
        console.error('[WebSocket] Failed to send message:', error)
        this.messageQueue.push(data)
      }
    } else {
      // Queue message if not connected
      this.messageQueue.push(data)
    }
  }

  /**
   * Gracefully disconnect from WebSocket
   */
  disconnect(): Promise<void> {
    return new Promise((resolve) => {
      if (!this.ws) {
        resolve()
        return
      }

      this.isIntentionallyClosed = true
      this.stopHeartbeat()

      if (this.ws.readyState === WebSocket.OPEN) {
        const closeHandler = () => {
          this.ws?.removeEventListener('close', closeHandler)
          resolve()
        }
        this.ws.addEventListener('close', closeHandler)
        this.ws.close(1000, 'Client initiated close')

        // Timeout if close doesn't complete
        setTimeout(() => {
          if (this.ws) {
            this.ws.removeEventListener('close', closeHandler)
            this.ws = null
          }
          resolve()
        }, 2000)
      } else {
        this.ws = null
        resolve()
      }
    })
  }

  /**
   * Check if currently connected
   */
  isConnected(): boolean {
    return this.ws !== null && this.ws.readyState === WebSocket.OPEN
  }

  /**
   * Get current connection state
   */
  getState(): number {
    return this.ws?.readyState ?? WebSocket.CLOSED
  }

  /**
   * Attempt to reconnect with exponential backoff
   */
  private attemptReconnect(): void {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('[WebSocket] Max reconnection attempts reached')
      this.emit('error', { message: 'Failed to reconnect after max attempts' })
      return
    }

    this.reconnectAttempts++
    const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1)
    
    console.log(`[WebSocket] Attempting to reconnect in ${delay}ms (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`)

    setTimeout(() => {
      this.connect(this.url).catch(error => {
        console.error('[WebSocket] Reconnection failed:', error)
        this.attemptReconnect()
      })
    }, delay)
  }

  /**
   * Flush queued messages
   */
  private flushMessageQueue(): void {
    while (this.messageQueue.length > 0 && this.isConnected()) {
      const message = this.messageQueue.shift()
      this.send(message)
    }
  }

  /**
   * Start heartbeat to keep connection alive
   */
  private startHeartbeat(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval)
    }

    // Send ping every 30 seconds
    this.heartbeatInterval = setInterval(() => {
      if (this.isConnected()) {
        try {
          this.ws?.send(JSON.stringify({ type: 'ping' }))
        } catch (error) {
          console.error('[WebSocket] Failed to send ping:', error)
        }
      }
    }, 30000)
  }

  /**
   * Stop heartbeat
   */
  private stopHeartbeat(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval)
      this.heartbeatInterval = null
    }
  }

  /**
   * Reset service state
   */
  reset(): void {
    this.disconnect()
    this.listeners.clear()
    this.messageQueue = []
    this.reconnectAttempts = 0
    this.isIntentionallyClosed = false
    this.stopHeartbeat()
    this.initializeEventMap()
  }
}

export default WebSocketService
