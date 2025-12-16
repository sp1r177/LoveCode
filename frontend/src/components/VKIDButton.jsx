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

  const handleVKLogin = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      
      // Redirect to public VK ID endpoint
      window.location.href = '/vkid.php'
    } catch (err) {
      console.error('VK ID init error:', err)
      let errorMessage = 'Ошибка авторизации'
      if (err.response) {
        if (err.response.status === 403) {
          errorMessage = 'Доступ запрещен. Проверьте настройки CORS и конфигурацию приложения VK.'
        } else {
          errorMessage = err.response.data?.error || `Ошибка сервера: ${err.response.status}`
        }
      } else if (err.request) {
        errorMessage = 'Нет ответа от сервера. Проверьте подключение к интернету.'
      } else {
        errorMessage = err.message || 'Ошибка авторизации'
      }
      setError(errorMessage)
      setLoading(false)
    }
  }, [API_URL])

  const vkidOnSuccess = useCallback(async (data) => {
    console.log('VK ID success data:', data)
    
    if (!data || !data.access_token) {
      setError('Не получен токен от VK ID')
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)

    try {
      // Отправляем токен на бэкенд для создания/получения пользователя и JWT
      console.log('Sending token to backend...', { API_URL, token: data.access_token?.substring(0, 20) + '...' })
      
      const response = await axios.post(
        `${API_URL}/api/auth/vkid`,
        {
          access_token: data.access_token,
        },
        {
          headers: {
            'Content-Type': 'application/json',
          },
          withCredentials: true,
        }
      )

      if (response.data.token) {
        login(response.data.token)
        navigate('/analyze')
      } else {
        throw new Error('Не получен токен от сервера')
      }
    } catch (err) {
      console.error('VK ID authentication error:', err)
      // Улучшенная обработка ошибок
      let errorMessage = 'Ошибка авторизации';
      if (err.response) {
        // Сервер ответил ошибкой
        if (err.response.status === 403) {
          errorMessage = 'Доступ запрещен. Проверьте настройки CORS и конфигурацию приложения VK.';
        } else {
          errorMessage = err.response.data?.error || `Ошибка сервера: ${err.response.status}`;
        }
      } else if (err.request) {
        // Запрос был сделан, но ответа не получено
        errorMessage = 'Нет ответа от сервера. Проверьте подключение к интернету.';
      } else {
        // Что-то пошло не так при настройке запроса
        errorMessage = err.message || 'Ошибка авторизации';
      }
      setError(errorMessage)
      setLoading(false)
    }
  }, [API_URL, login, navigate])

  const vkidOnError = useCallback((error) => {
    console.error('VK ID error:', error)
    setError('Ошибка авторизации VK ID. Попробуйте ещё раз.')
    setLoading(false)
  }, [])

  const initializeVKID = useCallback(() => {
    if (!window.VKIDSDK || !containerRef.current || initialized) {
      return
    }

    const VKID = window.VKIDSDK

    try {
      // Используем точные значения из примера
      const appId = import.meta.env.VITE_VK_APP_ID || '54395556'
      const redirectUrl = import.meta.env.VITE_VK_REDIRECT_URI || 'https://flirt-ai.ru/vk-callback.php'

      console.log('Initializing VK ID OneTap with:', { appId, redirectUrl })

      // Используем правильную конфигурацию для inline авторизации
      VKID.Config.init({
        app: parseInt(appId),
        redirectUrl: redirectUrl,
        responseMode: VKID.ConfigResponseMode.Code, // Используем Code response mode
        source: VKID.ConfigSource.LOWCODE,
        scope: 'email', // Добавляем scope для получения email
      })

      const oneTap = new VKID.OneTap()

      oneTap
        .render({
          container: containerRef.current,
          showAlternativeLogin: true,
        })
        .on(VKID.WidgetEvents.ERROR, vkidOnError)
        .on(VKID.OneTapInternalEvents.LOGIN_SUCCESS, function (payload) {
          console.log('VK ID OneTap LOGIN_SUCCESS:', payload)
          
          const code = payload.code
          const deviceId = payload.device_id

          if (!code || !deviceId) {
            vkidOnError(new Error('Не получены необходимые данные от VK ID'))
            return
          }

          setLoading(true)
          setError(null)

          // Обмен кода на токен через VK ID SDK
          VKID.Auth.exchangeCode(code, deviceId)
            .then(vkidOnSuccess)
            .catch(vkidOnError)
        })
        
      // Предотвращаем открытие новых окон/вкладок при клике
      if (containerRef.current) {
        const handleClick = function(e) {
          // Останавливаем всплытие события для всех кликов внутри контейнера
          e.stopPropagation();
          
          // Предотвращаем действие по умолчанию для ссылок
          const target = e.target;
          if (target.tagName === 'A' || target.closest('a')) {
            e.preventDefault();
          }
        };
        
        // Добавляем обработчик ко всем элементам внутри контейнера
        containerRef.current.addEventListener('click', handleClick, true);
        
        // Сохраняем ссылку на функцию для возможности удаления
        containerRef.current._vkClickHandler = handleClick;
      }

      setInitialized(true)
    } catch (err) {
      console.error('Failed to initialize VK ID OneTap:', err)
      setError(`Не удалось инициализировать VK ID: ${err.message}`)
    }
  }, [initialized, vkidOnSuccess, vkidOnError])


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

      // Таймаут через 10 секунд - если SDK не загрузился, показываем ошибку
      setTimeout(() => {
        if (checkInterval) {
          clearInterval(checkInterval)
          if (!window?.VKIDSDK) {
            setError('Не удалось загрузить VK ID SDK. Проверьте подключение к интернету и консоль браузера для деталей.')
            console.error('VK ID SDK не загрузился за 10 секунд. Проверьте:')
            console.error('1. Подключение к интернету')
            console.error('2. Блокировку скриптов браузером/расширениями')
            console.error('3. Консоль браузера на наличие ошибок CORS или загрузки')
          }
        }
      }, 10000)
    }

    return () => {
      if (checkInterval) {
        clearInterval(checkInterval)
      }
      // Удаляем обработчик событий при размонтировании
      if (containerRef.current && containerRef.current._vkClickHandler) {
        containerRef.current.removeEventListener('click', containerRef.current._vkClickHandler, true);
      }
    }
  }, [initializeVKID, initialized])

  // Если SDK не загрузился и нет ошибки, показываем сообщение
  if (typeof window !== 'undefined' && !window.VKIDSDK && !error && !initialized) {
    return (
      <div className={`flex flex-col items-center ${className}`}>
        <p className="text-sm text-gray-500">Загрузка VK ID...</p>
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