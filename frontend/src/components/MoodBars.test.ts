import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MoodBars from './MoodBars.vue'

describe('MoodBars', () => {
  it('renders the four moods, dominant first, with proportional widths', () => {
    const wrapper = mount(MoodBars, {
      props: {
        moods: [
          { mood: 'festif', count: 8 },
          { mood: 'chill', count: 4 },
        ],
      },
    })

    const rows = wrapper.findAll('[data-testid="mood-bar"]')
    expect(rows).toHaveLength(4)
    expect(rows[0]!.text()).toContain('Festif')
    expect(rows[0]!.find('.bg-pink').attributes('style')).toContain('width: 100%')
    expect(rows[1]!.text()).toContain('Chill')
    expect(rows[1]!.find('.bg-cyan').attributes('style')).toContain('width: 50%')
  })

  it('names the dominant mood', () => {
    const wrapper = mount(MoodBars, {
      props: { moods: [{ mood: 'afterwork', count: 3 }] },
    })

    expect(wrapper.find('[data-testid="mood-dominant"]').text()).toContain('Afterwork')
  })

  it('shows no dominant mood when everything is at zero', () => {
    const wrapper = mount(MoodBars, { props: { moods: [] } })

    expect(wrapper.find('[data-testid="mood-dominant"]').exists()).toBe(false)
  })
})
