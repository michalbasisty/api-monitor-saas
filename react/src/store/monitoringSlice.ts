import { createSlice, PayloadAction } from '@reduxjs/toolkit'

interface MonitoringResult {
  endpoint_id: number
  response_time: number
  status_code: number | null
  error_message: string | null
}

interface MonitoringState {
  results: MonitoringResult[]
  isConnected: boolean
}

const initialState: MonitoringState = {
  results: [],
  isConnected: false,
}

const monitoringSlice = createSlice({
  name: 'monitoring',
  initialState,
  reducers: {
    addResult: (state, action: PayloadAction<MonitoringResult>) => {
      state.results.unshift(action.payload)
      // Keep only last 50 results
      if (state.results.length > 50) {
        state.results = state.results.slice(0, 50)
      }
    },
    setConnected: (state, action: PayloadAction<boolean>) => {
      state.isConnected = action.payload
    },
    clearResults: (state) => {
      state.results = []
    },
  },
})

export const { addResult, setConnected, clearResults } = monitoringSlice.actions
export default monitoringSlice.reducer
