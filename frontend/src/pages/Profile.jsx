import { useEffect, useState } from 'react'
import { useAuth } from '../contexts/AuthContext'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { getApiUrl } from '../utils/api'

export default function Profile() {
  const { user, refreshProfile } = useAuth()
  const navigate = useNavigate()
  const [history, setHistory] = useState([])
  const [loading, setLoading] = useState(true)
  const API_URL = getApiUrl()

  useEffect(() => {
    fetchHistory()
    refreshProfile()
  }, [])

  const fetchHistory = async () => {
    try {
      const response = await axios.get(`${API_URL}/api/history`)
      setHistory(response.data.analyses || [])
    } catch (error) {
      console.error('Failed to fetch history:', error)
    } finally {
      setLoading(false)
    }
  }

  if (!user) {
    return (
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="text-center text-gray-500">Загрузка...</div>
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Профиль</h1>

      <div className="bg-white border border-gray-200 rounded-lg p-6 mb-8">
        <div className="flex items-center gap-4 mb-6">
          {user.user?.avatar_url && (
            <img
              src={user.user.avatar_url}
              alt="Avatar"
              className="w-16 h-16 rounded-full"
            />
          )}
          <div>
            <h2 className="text-xl font-semibold text-gray-900">
              {user.user?.first_name} {user.user?.last_name}
            </h2>
            <p className="text-gray-500">ID: {user.user?.id}</p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 className="text-sm font-medium text-gray-500 mb-2">Тариф</h3>
            <p className="text-lg font-semibold text-gray-900">
              {user.subscription?.name || 'Free'}
            </p>
            {user.subscription?.expires_at && (
              <p className="text-sm text-gray-500">
                До: {new Date(user.subscription.expires_at).toLocaleDateString('ru-RU')}
              </p>
            )}
          </div>

          <div>
            <h3 className="text-sm font-medium text-gray-500 mb-2">Использование</h3>
            <p className="text-lg font-semibold text-gray-900">
              {user.usage?.used || 0} / {user.usage?.limit || 0} анализов
            </p>
            <div className="mt-2 w-full bg-gray-200 rounded-full h-2">
              <div
                className="bg-primary-600 h-2 rounded-full"
                style={{
                  width: `${Math.min(100, ((user.usage?.used || 0) / (user.usage?.limit || 1)) * 100)}%`,
                }}
              />
            </div>
          </div>
        </div>

        <div className="mt-6">
          <button
            onClick={() => navigate('/pricing')}
            className="bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors"
          >
            Изменить тариф
          </button>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">
          История анализов
        </h2>
        {loading ? (
          <div className="text-center text-gray-500 py-8">Загрузка...</div>
        ) : history.length === 0 ? (
          <div className="text-center text-gray-500 py-8">
            История анализов пуста
          </div>
        ) : (
          <div className="space-y-4">
            {history.map((analysis) => (
              <div
                key={analysis.id}
                className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors"
              >
                <div className="flex justify-between items-start mb-2">
                  <p className="text-sm text-gray-500">
                    {new Date(analysis.created_at).toLocaleString('ru-RU')}
                  </p>
                  <span className="text-xs text-gray-400">
                    {analysis.session_id.substring(0, 8)}...
                  </span>
                </div>
                <p className="text-gray-700 mb-2">{analysis.input_preview}</p>
                {analysis.result?.short_summary && (
                  <p className="text-sm text-gray-600 italic">
                    {analysis.result.short_summary.substring(0, 150)}...
                  </p>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

