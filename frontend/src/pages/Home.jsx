import { useNavigate, Link } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axios from 'axios'

export default function Home() {
  const { token } = useAuth()
  const navigate = useNavigate()
  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080'

  const handleVkLogin = async () => {
    try {
      const response = await axios.post(`${API_URL}/api/auth/vk-init`)
      window.location.href = response.data.auth_url
    } catch (error) {
      console.error('Failed to init VK auth:', error)
      alert('Ошибка авторизации. Попробуйте позже.')
    }
  }

  const handleGetStarted = () => {
    if (token) {
      navigate('/analyze')
    } else {
      handleVkLogin()
    }
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-20">
      <div className="text-center">
        <h1 className="text-4xl sm:text-5xl font-bold text-gray-900 mb-6">
          AI-ассистент по анализу переписок
        </h1>
        <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
          Получите глубокий анализ ваших диалогов: тональность, проблемные места
          и готовые варианты ответов для эффективной коммуникации.
        </p>
        <button
          onClick={handleGetStarted}
          className="bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-8 rounded-lg text-lg transition-colors"
        >
          {token ? 'Начать анализ' : 'Войти через VK'}
        </button>
        {!token && (
          <p className="mt-4 text-sm text-gray-600">
            Авторизуясь через VK ID, вы принимаете условия{' '}
            <Link to="/privacy" className="text-primary-600 hover:text-primary-700 underline">
              Политики конфиденциальности
            </Link>
            {' '}и{' '}
            <Link to="/terms" className="text-primary-600 hover:text-primary-700 underline">
              Пользовательского соглашения
            </Link>
            .
          </p>
        )}
      </div>

      <div className="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div className="text-center">
          <div className="text-3xl mb-4">📊</div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            Анализ тональности
          </h3>
          <p className="text-gray-600">
            Определение эмоциональной окраски каждого сообщения
          </p>
        </div>
        <div className="text-center">
          <div className="text-3xl mb-4">💡</div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            Варианты ответов
          </h3>
          <p className="text-gray-600">
            Готовые формулировки для разных ситуаций
          </p>
        </div>
        <div className="text-center">
          <div className="text-3xl mb-4">🎯</div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            Проблемные места
          </h3>
          <p className="text-gray-600">
            Выявление конфликтных моментов в диалоге
          </p>
        </div>
      </div>
    </div>
  )
}

