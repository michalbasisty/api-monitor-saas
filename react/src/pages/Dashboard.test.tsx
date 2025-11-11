import React from 'react'
import { Provider } from 'react-redux'
import { configureStore } from '@reduxjs/toolkit'
import { render, screen } from '@testing-library/react'
import Dashboard from './Dashboard'
import monitoringReducer, { addResult, setConnected } from '../store/monitoringSlice'

class WSStub {
  public onopen: (() => void) | null = null
  public onclose: (() => void) | null = null
  public onmessage: ((ev: MessageEvent) => void) | null = null
  constructor(url: string) {}
  close() { if (this.onclose) this.onclose() }
}

// @ts-ignore
global.WebSocket = WSStub

function setup() {
  const store = configureStore({ reducer: { monitoring: monitoringReducer } })
  const utils = render(
    <Provider store={store}>
      <Dashboard />
    </Provider>
  )
  return { store, ...utils }
}

describe('Dashboard (React)', () => {
  it('renders headings', () => {
    setup()
    expect(screen.getByText(/Dashboard/i)).toBeInTheDocument()
    expect(screen.getByText(/Real-time API monitoring dashboard/i)).toBeInTheDocument()
  })

  it('updates connection status via WebSocket events', () => {
    const { store } = setup()
    const ws = new WSStub('ws://test')

    // Simulate open
    if (ws.onopen) ws.onopen()
    store.dispatch(setConnected(true))
    expect(store.getState().monitoring.isConnected).toBe(true)

    // Simulate message
    const payload = { endpoint_id: '1', response_time: 123, status_code: 200, error_message: null }
    if (ws.onmessage) ws.onmessage(new MessageEvent('message', { data: JSON.stringify(payload) }))
    store.dispatch(addResult(payload))
    expect(store.getState().monitoring.results.length).toBeGreaterThanOrEqual(1)

    // Simulate close
    if (ws.onclose) ws.onclose()
    store.dispatch(setConnected(false))
    expect(store.getState().monitoring.isConnected).toBe(false)
  })
})
