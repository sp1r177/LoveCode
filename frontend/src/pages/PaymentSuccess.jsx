import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axios from 'axios'
import { getApiUrl } from '../utils/api'

export default function PaymentSuccess() {
  const [searchParams] = useSearchParams()
  const { refreshProfile } = useAuth()
  const navigate = useNavigate()
  const [status, setStatus] = useState('loading')
  const API_URL = getApiUrl()

  useEffect(() => {
    const paymentId = searchParams.get('payment_id')
    if (paymentId) {
      verifyPayment(paymentId)
    } else {
      setStatus('error')
    }
  }, [])

  const verifyPayment = async (paymentId) => {
    try {
      const response = await axios.get(`${API_URL}/api/payment/verify/${paymentId}`)
      if (response.data.status === 'completed') {
        setStatus('success')
        refreshProfile()
        setTimeout(() => navigate('/profile'), 2000)
      } else {
        setStatus('pending')
      }
    } catch (error) {
      console.error('Payment verification error:', error)
      setStatus('error')
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="max-w-md mx-auto px-4 text-center">
        {status === 'loading' && (
          <div>
            <div className="text-4xl mb-4">⏳</div>
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">
              Проверка платежа...
            </h2>
          </div>
        )}
        {status === 'success' && (
          <div>
            <div className="text-4xl mb-4">✅</div>
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">
              Платёж успешно обработан!
            </h2>
            <p className="text-gray-600">Перенаправление в профиль...</p>
          </div>
        )}
        {status === 'pending' && (
          <div>
            <div className="text-4xl mb-4">⏳</div>
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">
              Платёж обрабатывается
            </h2>
            <p className="text-gray-600">
              Это может занять несколько минут. Обновите страницу позже.
            </p>
          </div>
        )}
        {status === 'error' && (
          <div>
            <div className="text-4xl mb-4">❌</div>
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">
              Ошибка при обработке платежа
            </h2>
            <p className="text-gray-600 mb-4">
              Обратитесь в поддержку, если платёж был выполнен.
            </p>
            <button
              onClick={() => navigate('/profile')}
              className="bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2 px-4 rounded-lg"
            >
              Перейти в профиль
            </button>
          </div>
        )}
      </div>
    </div>
  )
}

