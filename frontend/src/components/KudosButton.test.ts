import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import KudosButton from './KudosButton.vue'

describe('KudosButton', () => {
  it('renders the count and emits toggle on click', async () => {
    const wrapper = mount(KudosButton, { props: { count: 4, active: false } })

    expect(wrapper.find('[data-testid="kudos-count"]').text()).toBe('4')
    expect(wrapper.find('[data-testid="kudos-button"]').attributes('aria-pressed')).toBe(
      'false',
    )

    await wrapper.find('[data-testid="kudos-button"]').trigger('click')

    expect(wrapper.emitted('toggle')).toHaveLength(1)
  })

  it('highlights when active', () => {
    const wrapper = mount(KudosButton, { props: { count: 1, active: true } })

    const button = wrapper.find('[data-testid="kudos-button"]')
    expect(button.attributes('aria-pressed')).toBe('true')
    expect(button.classes()).toContain('glow-gold')
  })
})
