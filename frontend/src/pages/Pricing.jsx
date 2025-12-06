import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axios from 'axios'

export default function Pricing() {
  const { token, user } = useAuth()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(null)
  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080'

  const handleSubscribe = async (plan) => {
    if (!token) {
      alert('Необходима авторизация')
      return
    }

    setLoading(plan)
    try {
      const response = await axios.post(`${API_URL}/api/payment/create-session`, {
        plan,
      })
      window.location.href = response.data.payment_url
    } catch (error) {
      console.error('Payment error:', error)
      alert('Ошибка при создании платежа')
      setLoading(null)
    }
  }

  const plans = [
    {
      id: 'free',
      name: 'Free',
      price: 0,
      analyses: 5,
      features: [
        'Базовый анализ переписок',
        '2 варианта ответов',
        'Анализ тональности',
        'Выявление проблемных мест',
      ],
      current: user?.subscription?.plan === 'free',
    },
    {
      id: 'pro',
      name: 'Pro',
      price: 299,
      analyses: 100,
      features: [
        'Все функции Free',
        '100 анализов в месяц',
        '4 варианта ответов',
        'Приоритетная обработка',
        'История без ограничений',
      ],
      current: user?.subscription?.plan === 'pro',
    },
    {
      id: 'ultra',
      name: 'Ultra',
      price: 499,
      analyses: 500,
      features: [
        'Все функции Pro',
        '500 анализов в месяц',
        'Улучшенный тональный анализ',
        'AI-коуч по коммуникации',
        'Приоритетная поддержка',
      ],
      current: user?.subscription?.plan === 'ultra',
    },
  ]

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div className="text-center mb-12">
        <h1 className="text-4xl font-bold text-gray-900 mb-4">Тарифы</h1>
        <p className="text-xl text-gray-600">
          Выберите план, который подходит вам
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
        {plans.map((plan) => (
          <div
            key={plan.id}
            className={`border rounded-lg p-6 ${
              plan.current
                ? 'border-primary-500 bg-primary-50'
                : 'border-gray-200 bg-white'
            }`}
          >
            <div className="text-center mb-6">
              <h3 className="text-2xl font-bold text-gray-900 mb-2">
                {plan.name}
              </h3>
              <div className="text-4xl font-bold text-gray-900 mb-1">
                {plan.price === 0 ? 'Бесплатно' : `${plan.price} ₽`}
              </div>
              {plan.price > 0 && (
                <div className="text-sm text-gray-500">в месяц</div>
              )}
            </div>

            <ul className="space-y-3 mb-6">
              <li className="text-sm text-gray-600">
                <strong>{plan.analyses}</strong> анализов в месяц
              </li>
              {plan.features.map((feature, idx) => (
                <li key={idx} className="text-sm text-gray-700 flex items-start">
                  <span className="text-green-500 mr-2">✓</span>
                  {feature}
                </li>
              ))}
            </ul>

            {plan.current ? (
              <div className="text-center py-3 px-4 bg-primary-100 text-primary-700 rounded-lg font-medium">
                Текущий тариф
              </div>
            ) : plan.id === 'free' ? (
              <div className="text-center py-3 px-4 text-gray-500 rounded-lg">
                По умолчанию
              </div>
            ) : (
              <button
                onClick={() => handleSubscribe(plan.id)}
                disabled={loading === plan.id}
                className="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {loading === plan.id ? 'Обработка...' : 'Подписаться'}
              </button>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}

