import { useEffect, useRef, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axios from 'axios'
import { getApiUrl } from '../utils/api'

export default function VKIDButton({ className = '' }) {
  const containerRef = useRef(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [initialized, setInitialized] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()
  const API_URL = getApiUrl()

  const initializeVKID = useCallback(() => {
    if (!window.VKIDSDK || !containerRef.current || initialized) {
      return
    }

    const VKID = window.VKIDSDK

    try {
      // Получаем app_id из переменных окружения
      // Если не задано, можно попробовать получить с бэкенда или использовать значение из примера
      const appId = import.meta.env.VITE_VK_APP_ID || '54395556'
      
      // Для VK ID SDK redirectUrl должен быть URL на вашем домене
      // Можно настроить через переменные окружения или использовать текущий домен
      // Примечание: URL должен совпадать с настройками в VK приложении
      const redirectUrl = import.meta.env.VITE_VK_REDIRECT_URI || 
        `${window.location.origin}/auth/vk-callback`

      VKID.Config.init({
        app: parseInt(appId),
        redirectUrl: redirectUrl,
        responseMode: VKID.ConfigResponseMode.Callback,
        source: VKID.ConfigSource.LOWCODE,
        scope: 'email',
      })

      const oAuth = new VKID.OAuthList()

      oAuth
        .render({
          container: containerRef.current,
          oauthList: ['vkid'],
        })
        .on(VKID.WidgetEvents.ERROR, (error) => {
          console.error('VK ID error:', error)
          setError('Ошибка авторизации VK ID')
          setLoading(false)
        })
        .on(VKID.OAuthListInternalEvents.LOGIN_SUCCESS, async function (payload) {
          const code = payload.code
          const deviceId = payload.device_id

          setLoading(true)
          setError(null)

          try {
            // Обмен кода на токен через VK ID SDK
            const authData = await VKID.Auth.exchangeCode(code, deviceId)

            if (!authData || !authData.access_token) {
              throw new Error('Не удалось получить токен от VK ID')
            }

            // Отправляем токен на бэкенд для создания/получения пользователя и JWT
            const response = await axios.post(`${API_URL}/api/auth/vkid`, {
              access_token: authData.access_token,
            })

            if (response.data.token) {
              login(response.data.token)
              navigate('/analyze')
            } else {
              throw new Error('Не получен токен от сервера')
            }
          } catch (err) {
            console.error('VK ID authentication error:', err)
            const errorMessage = err.response?.data?.error || err.message || 'Ошибка авторизации'
            setError(errorMessage)
            setLoading(false)
          }
        })

      setInitialized(true)
    } catch (err) {
      console.error('Failed to initialize VK ID:', err)
      setError('Не удалось инициализировать VK ID. Проверьте настройки приложения.')
    }
  }, [initialized, API_URL, login, navigate])

  useEffect(() => {
    let checkInterval = null

    const initialize = () => {
      if (!containerRef.current || typeof window === 'undefined' || !window.VKIDSDK || initialized) {
        return
      }
      initializeVKID()
    }

    // Если SDK уже загружен, инициализируем сразу
    if (window?.VKIDSDK && containerRef.current) {
      initialize()
    } else {
      // Иначе ждем загрузки SDK
      checkInterval = setInterval(() => {
        if (window?.VKIDSDK && containerRef.current) {
          clearInterval(checkInterval)
          initialize()
        }
      }, 100)

      // Таймаут через 5 секунд - если SDK не загрузился, показываем ошибку
      setTimeout(() => {
        if (checkInterval) {
          clearInterval(checkInterval)
          if (!window?.VKIDSDK) {
            setError('Не удалось загрузить VK ID SDK. Проверьте подключение к интернету.')
          }
        }
      }, 5000)
    }

    return () => {
      if (checkInterval) {
        clearInterval(checkInterval)
      }
    }
  }, [initializeVKID, initialized])

  // Если SDK не загрузился, показываем сообщение
  if (typeof window !== 'undefined' && !window.VKIDSDK && !error) {
    return (
      <div className={`flex flex-col items-center ${className}`}>
        <p className="text-sm text-gray-500">Загрузка...</p>
      </div>
    )
  }

  return (
    <div className={`flex flex-col items-center ${className}`}>
      <div
        ref={containerRef}
        className="flex justify-center w-full"
        style={{ minHeight: '48px' }}
      />
      {loading && (
        <p className="mt-3 text-sm text-gray-500">Авторизация...</p>
      )}
      {error && (
        <div className="mt-3 text-sm text-red-500 text-center max-w-md">
          {error}
        </div>
      )}
    </div>
  )
}
