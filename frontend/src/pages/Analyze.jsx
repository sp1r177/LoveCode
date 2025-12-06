import { useState } from 'react'
import axios from 'axios'
import { useAuth } from '../contexts/AuthContext'

const TONE_COLORS = {
  neutral: 'bg-gray-200 text-gray-800',
  warm: 'bg-green-100 text-green-800',
  cold: 'bg-blue-100 text-blue-800',
  irritated: 'bg-red-100 text-red-800',
  anxious: 'bg-amber-100 text-amber-800',
}

const TONE_LABELS = {
  neutral: 'Нейтральный',
  warm: 'Тёплый',
  cold: 'Холодный',
  irritated: 'Раздражение',
  anxious: 'Тревожность',
}

export default function Analyze() {
  const [text, setText] = useState('')
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)
  const { user } = useAuth()
  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080'

  const handleAnalyze = async () => {
    if (!text.trim()) {
      setError('Введите текст переписки')
      return
    }

    setLoading(true)
    setError(null)
    setResult(null)

    try {
      const response = await axios.post(`${API_URL}/api/analyze-dialog`, {
        text: text.trim(),
      })
      setResult(response.data.result)
    } catch (err) {
      if (err.response?.status === 403) {
        setError('Превышен лимит анализов. Перейдите на платный тариф.')
      } else {
        setError(err.response?.data?.error || 'Ошибка при анализе')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-6">Анализ переписки</h1>

      {user && (
        <div className="mb-6 p-4 bg-blue-50 rounded-lg">
          <p className="text-sm text-gray-700">
            Использовано: {user.usage?.used || 0} / {user.usage?.limit || 0} анализов
          </p>
        </div>
      )}

      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Вставьте текст переписки
        </label>
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder="Пример:&#10;Анна: Привет, как дела?&#10;Иван: Нормально, а у тебя?&#10;Анна: Всё хорошо, спасибо!"
          className="w-full h-48 p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
        />
      </div>

      <button
        onClick={handleAnalyze}
        disabled={loading}
        className="bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-6 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {loading ? 'Анализирую...' : 'Проанализировать'}
      </button>

      {error && (
        <div className="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
          {error}
        </div>
      )}

      {result && (
        <div className="mt-8 space-y-6">
          <div className="bg-white border border-gray-200 rounded-lg p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">
              Краткое резюме
            </h2>
            <p className="text-gray-700">{result.short_summary}</p>
          </div>

          {result.messages && result.messages.length > 0 && (
            <div className="bg-white border border-gray-200 rounded-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                Анализ сообщений
              </h2>
              <div className="space-y-4">
                {result.messages.map((msg, idx) => (
                  <div key={idx} className="border-l-4 border-gray-200 pl-4">
                    <div className="flex items-center gap-2 mb-2">
                      <span className="font-medium text-gray-900">
                        {msg.author || 'Пользователь'}
                      </span>
                      <span
                        className={`px-2 py-1 rounded text-xs font-medium ${
                          TONE_COLORS[msg.tone] || TONE_COLORS.neutral
                        }`}
                      >
                        {TONE_LABELS[msg.tone] || msg.tone}
                      </span>
                    </div>
                    <p className="text-gray-700 mb-1">{msg.text}</p>
                    {msg.sentiment && (
                      <p className="text-xs text-gray-500">
                        Настроение: {msg.sentiment === 'positive' ? 'Позитивное' : msg.sentiment === 'negative' ? 'Негативное' : 'Нейтральное'}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {result.issues && result.issues.length > 0 && (
            <div className="bg-white border border-gray-200 rounded-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                Проблемные места
              </h2>
              <ul className="list-disc list-inside space-y-2 text-gray-700">
                {result.issues.map((issue, idx) => (
                  <li key={idx}>{issue}</li>
                ))}
              </ul>
            </div>
          )}

          {result.reply_options && result.reply_options.length > 0 && (
            <div className="bg-white border border-gray-200 rounded-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                Варианты ответов
              </h2>
              <div className="space-y-4">
                {result.reply_options.map((option, idx) => (
                  <div key={idx} className="border border-gray-200 rounded-lg p-4">
                    <div className="text-sm font-medium text-gray-500 mb-2">
                      {option.type === 'soft' && 'Мягкий'}
                      {option.type === 'direct' && 'Прямой'}
                      {option.type === 'humor' && 'С юмором'}
                      {option.type === 'boundaries' && 'С границами'}
                    </div>
                    <p className="text-gray-900">{option.text}</p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

