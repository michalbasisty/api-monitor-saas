/**
 * useWebSocket Hook
 * 
 * React hook for WebSocket connection management
 * Handles:
 * - Connection lifecycle
 * - Event subscription
 * - Error handling
 * - Cleanup on unmount
 */

import { useEffect, useState, useCallback, useRef } from 'react'
import WebSocketService, { WebSocketEventType, WebSocketEventCallback } from '../services/websocket.service'

interface UseWebSocketOptions {
  url: string
  autoConnect?: boolean
  onConnect?: () => void
  onDisconnect?: () => void
  onError?: (error: Error | any) => void
}

interface UseWebSocketReturn {
  isConnected: boolean
  isLoading: boolean
  error: Error | null
  connect: () => Promise<void>
  disconnect: () => Promise<void>
  subscribe: (eventType: WebSocketEventType, callback: WebSocketEventCallback) => () => void
  send: (data: any) => void
}

export const useWebSocket = ({
  url,
  autoConnect = true,
  onConnect,
  onDisconnect,
  onError,
}: UseWebSocketOptions): UseWebSocketReturn => {
  const [isConnected, setIsConnected] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<Error | null>(null)
  const serviceRef = useRef(WebSocketService.getInstance())
  const mountedRef = useRef(true)
  const unsubscribeRef = useRef<(() => void)[]>([])

  // Connect to WebSocket
  const connect = useCallback(async () => {
    if (serviceRef.current.isConnected()) {
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      await serviceRef.current.connect(url)
      if (mountedRef.current) {
        setIsConnected(true)
        setIsLoading(false)
        onConnect?.()
      }
    } catch (err) {
      const error = err instanceof Error ? err : new Error(String(err))
      if (mountedRef.current) {
        setError(error)
        setIsLoading(false)
        onError?.(error)
      }
    }
  }, [url, onConnect, onError])

  // Disconnect from WebSocket
  const disconnect = useCallback(async () => {
    await serviceRef.current.disconnect()
    if (mountedRef.current) {
      setIsConnected(false)
      onDisconnect?.()
    }
  }, [onDisconnect])

  // Subscribe to WebSocket events
  const subscribe = useCallback(
    (eventType: WebSocketEventType, callback: WebSocketEventCallback) => {
      const unsubscribe = serviceRef.current.subscribe(eventType, callback)
      unsubscribeRef.current.push(unsubscribe)
      return unsubscribe
    },
    []
  )

  // Send data through WebSocket
  const send = useCallback((data: any) => {
    serviceRef.current.send(data)
  }, [])

  // Setup connection on mount and cleanup on unmount
  useEffect(() => {
    mountedRef.current = true

    if (autoConnect) {
      connect()
    }

    // Subscribe to connection events
    const unsubscribeConnected = serviceRef.current.subscribe('connected', () => {
      if (mountedRef.current) {
        setIsConnected(true)
        setError(null)
        onConnect?.()
      }
    })

    const unsubscribeDisconnected = serviceRef.current.subscribe('disconnected', () => {
      if (mountedRef.current) {
        setIsConnected(false)
        onDisconnect?.()
      }
    })

    const unsubscribeError = serviceRef.current.subscribe('error', (data) => {
      if (mountedRef.current) {
        const error = data.error instanceof Error ? data.error : new Error(data.message)
        setError(error)
        onError?.(error)
      }
    })

    return () => {
      mountedRef.current = false
      unsubscribeConnected()
      unsubscribeDisconnected()
      unsubscribeError()
      unsubscribeRef.current.forEach(unsubscribe => unsubscribe())
      unsubscribeRef.current = []
    }
  }, [autoConnect, connect, onConnect, onDisconnect, onError])

  return {
    isConnected,
    isLoading,
    error,
    connect,
    disconnect,
    subscribe,
    send,
  }
}

export default useWebSocket
