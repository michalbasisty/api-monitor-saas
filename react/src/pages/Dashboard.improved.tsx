import { useEffect, useState } from 'react'
import { useSelector, useDispatch } from 'react-redux'
import { RootState } from '../store'
import { addResult, setConnected } from '../store/monitoringSlice'
import useWebSocket from '../hooks/useWebSocket'
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

/**
 * Improved Dashboard Component
 * 
 * Features:
 * - Centralized WebSocket management via useWebSocket hook
 * - Proper error handling and reconnection
 * - Loading states
 * - Graceful cleanup on unmount
 * - Accessibility improvements
 */

function Dashboard() {
  const { results, isConnected } = useSelector((state: RootState) => state.monitoring)
  const dispatch = useDispatch()
  const [stats, setStats] = useState({
    totalEndpoints: 0,
    activeEndpoints: 0,
    averageResponseTime: 0,
    uptimePercentage: 0,
    alertsCount: 0,
  })

  // Use improved WebSocket hook
  const {
    isConnected: wsConnected,
    isLoading: wsLoading,
    error: wsError,
    connect,
    disconnect,
    subscribe,
  } = useWebSocket({
    url: import.meta.env.VITE_WS_URL || 'ws://localhost:8080/ws',
    autoConnect: true,
    onConnect: () => {
      console.log('Dashboard: WebSocket connected')
      dispatch(setConnected(true))
    },
    onDisconnect: () => {
      console.log('Dashboard: WebSocket disconnected')
      dispatch(setConnected(false))
    },
    onError: (error) => {
      console.error('Dashboard: WebSocket error:', error)
    },
  })

  // Subscribe to monitoring results
  useEffect(() => {
    const unsubscribe = subscribe('monitoring_result', (data) => {
      dispatch(addResult(data))
    })

    return () => {
      unsubscribe()
    }
  }, [dispatch, subscribe])

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      disconnect()
    }
  }, [disconnect])

  // Calculate statistics from results
  useEffect(() => {
    if (results.length === 0) return

    const validResults = results.filter(r => !r.error_message)
    const responseTimes = validResults.map(r => r.response_time)
    const averageResponseTime =
      responseTimes.length > 0
        ? Math.round(responseTimes.reduce((a, b) => a + b, 0) / responseTimes.length)
        : 0

    const uniqueEndpoints = new Set(results.map(r => r.endpoint_id))
    const successfulChecks = results.filter(r => !r.error_message).length
    const totalChecks = results.length
    const uptimePercentage = totalChecks > 0 ? Math.round((successfulChecks / totalChecks) * 100) : 0

    setStats({
      totalEndpoints: uniqueEndpoints.size,
      activeEndpoints: validResults.length > 0 ? uniqueEndpoints.size : 0,
      averageResponseTime,
      uptimePercentage,
      alertsCount: results.filter(r => r.status_code && r.status_code >= 400).length,
    })
  }, [results])

  // Prepare chart data
  const chartData = results
    .slice(0, 20)
    .reverse()
    .map((result, index) => ({
      time: index,
      responseTime: result.response_time,
    }))

  const renderConnectionStatus = () => {
    if (wsLoading) {
      return (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
          ⏳ Connecting...
        </span>
      )
    }

    if (wsError) {
      return (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
          ❌ Error: {wsError.message}
        </span>
      )
    }

    return (
      <span
        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
          wsConnected
            ? 'bg-green-100 text-green-800'
            : 'bg-red-100 text-red-800'
        }`}
      >
        {wsConnected ? '✅ Connected' : '❌ Disconnected'}
      </span>
    )
  }

  return (
    <div className="px-4 py-6 sm:px-0">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p className="mt-2 text-sm text-gray-700">
          Real-time API monitoring dashboard
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
        <StatCard
          icon="🔗"
          label="Total Endpoints"
          value={stats.totalEndpoints}
          color="green"
        />
        <StatCard
          icon="✅"
          label="Active"
          value={stats.activeEndpoints}
          color="green"
        />
        <StatCard
          icon="⚡"
          label="Avg Response"
          value={`${stats.averageResponseTime}ms`}
          color="blue"
        />
        <StatCard
          icon="📈"
          label="Uptime"
          value={`${stats.uptimePercentage}%`}
          color="purple"
        />
        <StatCard
          icon="🚨"
          label="Active Alerts"
          value={stats.alertsCount}
          color="red"
        />
        <div className="bg-white overflow-hidden shadow rounded-lg border-l-4 border-gray-500">
          <div className="p-5">
            <div className="flex items-center justify-between">
              <div className="flex-shrink-0">
                <div className="text-2xl">{wsConnected ? '🟢' : '🔴'}</div>
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Connection
                  </dt>
                  <dd className="mt-1">{renderConnectionStatus()}</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Charts and System Health */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-medium text-gray-900">
              Response Time Trend
            </h2>
            <button
              onClick={() => alert('Monitoring started!')}
              className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition"
              aria-label="Run monitoring check"
            >
              🔄 Run Check
            </button>
          </div>
          {chartData.length > 0 ? (
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="time" />
                  <YAxis />
                  <Tooltip />
                  <Line
                    type="monotone"
                    dataKey="responseTime"
                    stroke="#3b82f6"
                    strokeWidth={2}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          ) : (
            <div className="h-64 flex items-center justify-center text-gray-500">
              No data available
            </div>
          )}
        </div>

        <div className="bg-white shadow rounded-lg p-6">
          <h2 className="text-lg font-medium text-gray-900 mb-4">
            System Health
          </h2>
          <div className="space-y-4">
            <SystemHealthItem label="API Server" status="online" />
            <SystemHealthItem label="Go Monitor" status="online" />
            <SystemHealthItem label="Database" status="connected" />
            <SystemHealthItem
              label="WebSocket"
              status={wsConnected ? 'connected' : 'disconnected'}
            />
          </div>
        </div>
      </div>

      {/* Recent Results Table */}
      <div className="mt-8 bg-white shadow rounded-lg">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
            Recent Results
          </h3>
          {results.length === 0 ? (
            <div className="text-center py-12">
              <p className="text-gray-500">No monitoring results yet</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Endpoint ID
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Response Time
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status Code
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Error
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {results.slice(0, 10).map((result, index) => (
                    <tr key={index}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {result.endpoint_id}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {result.response_time}ms
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <StatusBadge code={result.status_code} />
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                        {result.error_message || '-'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

/**
 * StatCard Component
 */
interface StatCardProps {
  icon: string
  label: string
  value: string | number
  color: 'green' | 'blue' | 'purple' | 'red'
}

const StatCard: React.FC<StatCardProps> = ({ icon, label, value, color }) => {
  const colorMap = {
    green: 'border-green-500',
    blue: 'border-blue-500',
    purple: 'border-purple-500',
    red: 'border-red-500',
  }

  return (
    <div className={`bg-white overflow-hidden shadow rounded-lg border-l-4 ${colorMap[color]}`}>
      <div className="p-5">
        <div className="flex items-center">
          <div className="flex-shrink-0">
            <div className="text-2xl">{icon}</div>
          </div>
          <div className="ml-5 w-0 flex-1">
            <dl>
              <dt className="text-sm font-medium text-gray-500 truncate">
                {label}
              </dt>
              <dd className="text-lg font-medium text-gray-900">{value}</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  )
}

/**
 * SystemHealthItem Component
 */
interface SystemHealthItemProps {
  label: string
  status: 'online' | 'connected' | 'disconnected'
}

const SystemHealthItem: React.FC<SystemHealthItemProps> = ({ label, status }) => {
  const statusConfig = {
    online: { bg: 'bg-green-100', text: 'text-green-800', label: '✅ Online' },
    connected: { bg: 'bg-green-100', text: 'text-green-800', label: '✅ Connected' },
    disconnected: { bg: 'bg-red-100', text: 'text-red-800', label: '❌ Disconnected' },
  }

  const config = statusConfig[status]

  return (
    <div className="flex justify-between items-center">
      <span className="text-sm font-medium text-gray-700">{label}</span>
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
        {config.label}
      </span>
    </div>
  )
}

/**
 * StatusBadge Component
 */
interface StatusBadgeProps {
  code?: number
}

const StatusBadge: React.FC<StatusBadgeProps> = ({ code }) => {
  if (!code) return <span>N/A</span>

  let color = 'bg-green-100 text-green-800'
  if (code >= 400 && code < 500) {
    color = 'bg-yellow-100 text-yellow-800'
  } else if (code >= 500) {
    color = 'bg-red-100 text-red-800'
  }

  return <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${color}`}>{code}</span>
}

export default Dashboard
