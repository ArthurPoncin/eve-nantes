import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { VenueReviews } from '@/types/review'

vi.mock('@/api/reviews', () => ({
  fetchVenueReviews: vi.fn(),
  postVenueReview: vi.fn(),
}))

import ReviewsSection from './ReviewsSection.vue'
import { fetchVenueReviews, postVenueReview } from '@/api/reviews'

const mockedFetch = vi.mocked(fetchVenueReviews)
const mockedPost = vi.mocked(postVenueReview)

function makeReviews(overrides: Partial<VenueReviews> = {}): VenueReviews {
  return {
    average: 4.5,
    count: 2,
    reviews: [
      {
        id: 2,
        username: 'bob',
        rating: 4,
        comment: null,
        created_at: '2026-06-09T22:10:00+02:00',
      },
      {
        id: 1,
        username: 'alice',
        rating: 5,
        comment: 'Dancefloor incroyable.',
        created_at: '2026-06-08T23:30:00+02:00',
      },
    ],
    ...overrides,
  }
}

async function mountSection() {
  const wrapper = mount(ReviewsSection, {
    props: { slug: 'le-macadam' },
    global: { stubs: { RouterLink: true } },
  })
  await flushPromises()
  return wrapper
}

function authenticate(): void {
  localStorage.setItem('noctambule.token', 'tok_abc')
  setActivePinia(createPinia())
}

beforeEach(() => {
  mockedFetch.mockReset()
  mockedPost.mockReset()
  localStorage.clear()
  setActivePinia(createPinia())
})

describe('ReviewsSection', () => {
  it('renders the average rating and the list of reviews', async () => {
    mockedFetch.mockResolvedValue(makeReviews())
    const wrapper = await mountSection()

    expect(mockedFetch).toHaveBeenCalledWith('le-macadam')
    expect(wrapper.find('[data-testid="reviews-average"]').text()).toContain('4,5')
    expect(wrapper.text()).toContain('2 avis')

    const items = wrapper.findAll('[data-testid="review-item"]')
    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('bob')
    expect(items[1]!.text()).toContain('Dancefloor incroyable.')
  })

  it('shows an empty hint when the venue has no review yet', async () => {
    mockedFetch.mockResolvedValue(makeReviews({ average: null, count: 0, reviews: [] }))
    const wrapper = await mountSection()

    expect(wrapper.find('[data-testid="reviews-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="reviews-average"]').exists()).toBe(false)
  })

  it('invites anonymous visitors to log in instead of showing the form', async () => {
    mockedFetch.mockResolvedValue(makeReviews())
    const wrapper = await mountSection()

    expect(wrapper.find('[data-testid="review-form"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="reviews-login-cta"]').exists()).toBe(true)
  })

  it('lets an authenticated user pick a rating and post a review', async () => {
    authenticate()
    mockedFetch.mockResolvedValue(makeReviews())
    mockedPost.mockResolvedValue({
      id: 3,
      username: 'arthur',
      rating: 4,
      comment: 'Très bonne prog.',
      created_at: '2026-06-10T21:00:00+02:00',
    })
    const wrapper = await mountSection()

    expect(wrapper.find('[data-testid="reviews-login-cta"]').exists()).toBe(false)

    await wrapper.find('[data-testid="review-star-4"]').trigger('click')
    await wrapper.find('[data-testid="review-comment"]').setValue('Très bonne prog.')
    await wrapper.find('[data-testid="review-form"]').trigger('submit')
    await flushPromises()

    expect(mockedPost).toHaveBeenCalledWith('le-macadam', {
      rating: 4,
      comment: 'Très bonne prog.',
    })
    // La liste est rechargée après l'envoi pour refléter la nouvelle moyenne.
    expect(mockedFetch).toHaveBeenCalledTimes(2)
  })

  it('does not post while no rating is selected', async () => {
    authenticate()
    mockedFetch.mockResolvedValue(makeReviews())
    const wrapper = await mountSection()

    await wrapper.find('[data-testid="review-form"]').trigger('submit')
    await flushPromises()

    expect(mockedPost).not.toHaveBeenCalled()
  })

  it('renders nothing when the API fails', async () => {
    mockedFetch.mockRejectedValue(new Error('500'))
    const wrapper = await mountSection()

    expect(wrapper.find('[data-testid="reviews-section"]').exists()).toBe(false)
  })
})
