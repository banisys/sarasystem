{{-- Alpine side of the admin clock field, App\Filament\Forms\ClockPicker (ADR 0067). --}}
<script>
    document.addEventListener('alpine:init', () => {
        const pad = (number) => String(number).padStart(2, '0')

        const latinDigits = (text) => text
            .replace(/[۰-۹]/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
            .replace(/[٠-٩]/g, (digit) => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))

        /**
         * «930», «9:30», «9.30», «۱۷۳۰», «17» → { hour, minute }; null when it isn't a time.
         */
        const parse = (text) => {
            const value = latinDigits(String(text ?? '')).trim().replace(/[.,٫\s-]/g, ':')
            let match = value.match(/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/)

            if (! match) {
                const digits = value.match(/^\d{1,4}$/) ? value : null

                if (digits === null) {
                    return null
                }

                match = digits.length <= 2
                    ? [digits, digits, '0']
                    : [digits, digits.slice(0, digits.length - 2), digits.slice(-2)]
            }

            const hour = Number(match[1])
            const minute = Number(match[2])

            return (hour < 24 && minute < 60) ? { hour, minute } : null
        }

        const format = (time) => time ? `${pad(time.hour)}:${pad(time.minute)}` : null

        /** The time part of a state («09:30», «09:30:00» or «2026-11-08 09:30:00»). */
        const fromState = (state) => {
            const match = String(state ?? '').match(/(\d{1,2}):(\d{2})/)

            return match ? { hour: Number(match[1]), minute: Number(match[2]) } : null
        }

        window.Alpine.data('saraClockPicker', ({ state, minutesStep, isEditable }) => ({
            state,
            minutesStep,
            text: '',
            hour: null,
            minute: null,
            isOpen: false,
            opensUp: false,
            nowHour: new Date().getHours(),
            hours: Array.from({ length: 24 }, (_, index) => index),
            minutes: Array.from({ length: Math.ceil(60 / minutesStep) }, (_, index) => index * minutesStep),

            init() {
                this.show(fromState(this.state))

                this.$watch('state', (state) => {
                    const time = fromState(state)
                    const current = parse(this.text)

                    if (! time || ! current || time.hour !== current.hour || time.minute !== current.minute) {
                        this.show(time)
                    }
                })
            },

            pad,

            show(time) {
                this.hour = time?.hour ?? null
                this.minute = time?.minute ?? null
                this.text = format(time) ?? ''
            },

            set(time) {
                this.show(time)

                if (format(fromState(this.state)) !== format(time)) {
                    this.state = format(time)
                }
            },

            open() {
                if (isEditable) {
                    this.nowHour = new Date().getHours()

                    // Upwards when the panel (about 21rem) doesn't fit below the field but does above it.
                    const field = this.$refs.input.getBoundingClientRect()
                    const panelHeight = 21 * parseFloat(getComputedStyle(document.documentElement).fontSize)
                    this.opensUp = (window.innerHeight - field.bottom < panelHeight) && (field.top > panelHeight)
                    this.isOpen = true
                }
            },

            close() {
                this.isOpen = false
            },

            /** Keeps what is typed to digits and a colon, adding the colon as soon as it is clear where it goes. */
            typed(event) {
                let value = latinDigits(event.target.value).replace(/[.,٫\s-]/g, ':').replace(/[^\d:]/g, '')

                if (! value.includes(':')) {
                    if (value.length >= 3 || (value.length === 2 && Number(value) > 23)) {
                        value = Number(value.slice(0, 2)) <= 23
                            ? `${value.slice(0, 2)}:${value.slice(2, 4)}`
                            : `0${value[0]}:${value.slice(1, 3)}`
                    } else if (value.length === 1 && Number(value) > 2) {
                        value = `0${value}:`
                    }
                }

                value = value.replace(/:+/g, ':').slice(0, 5)
                this.text = value
                event.target.value = value

                const time = /^\d{1,2}:\d{2}$/.test(value) ? parse(value) : null

                if (time) {
                    this.set(time)
                } else {
                    const typedHour = Number(value.match(/^(\d{1,2})/)?.[1] ?? NaN)
                    this.hour = typedHour < 24 ? typedHour : null
                    this.minute = null
                }
            },

            /** Saves what was typed; something that isn't a time goes back to the saved one. */
            commit(shouldClose = false) {
                if (this.text.trim() === '') {
                    this.set(null)
                } else {
                    const time = parse(this.text)

                    time ? this.set(time) : this.show(fromState(this.state))
                }

                if (shouldClose) {
                    this.close()
                }
            },

            step(direction, byHour = false) {
                const time = parse(this.text) ?? fromState(this.state)
                let total

                if (! time) {
                    const now = new Date()
                    total = now.getHours() * 60 + Math.round(now.getMinutes() / this.minutesStep) * this.minutesStep
                } else if (byHour) {
                    total = time.hour * 60 + time.minute + direction * 60
                } else {
                    const minutes = time.hour * 60 + time.minute
                    const snapped = direction > 0
                        ? Math.floor(minutes / this.minutesStep) * this.minutesStep + this.minutesStep
                        : Math.ceil(minutes / this.minutesStep) * this.minutesStep - this.minutesStep
                    total = snapped
                }

                total = ((total % 1440) + 1440) % 1440
                this.set({ hour: Math.floor(total / 60), minute: total % 60 })
            },

            pickHour(hour) {
                this.set({ hour, minute: this.minute ?? 0 })
            },

            pickMinute(minute) {
                this.set({ hour: this.hour ?? this.nowHour, minute })
                this.close()
            },

            pickNow() {
                const now = new Date()
                this.set({ hour: now.getHours(), minute: now.getMinutes() })
                this.close()
            },

            clear() {
                this.set(null)
                this.$refs.input.focus()
            },
        }))
    })
</script>
