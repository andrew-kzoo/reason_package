////////////////////////////////////////////////////////
//
// GEM - Graphics Environment for Multimedia
//
// zmoelnig@iem.kug.ac.at
//
// Implementation file
//
//    Copyright (c) 1997-1999 Mark Danks.
//    Copyright (c) G�nther Geiger.
//    Copyright (c) 2001-2002 IOhannes m zmoelnig. forum::f�r::uml�ute. IEM
//    Copyright (c) 2002 James Tittle
//
//    For information on usage and redistribution, and for a DISCLAIMER OF ALL
//    WARRANTIES, see the file, "GEM.LICENSE.TERMS" in this distribution.
//
/////////////////////////////////////////////////////////

#include "pix_movie.h"

#ifndef __APPLE__

CPPEXTERN_NEW_WITH_ONE_ARG(pix_movie, t_symbol *, A_DEFSYM)

/////////////////////////////////////////////////////////
//
// pix_movie
//
/////////////////////////////////////////////////////////
// Constructor
//
/////////////////////////////////////////////////////////
pix_movie :: pix_movie(t_symbol *filename) :
  pix_film(filename)
{
  // we don't want the additional in/outlet of [pix_texture]
  inlet_free(m_pixtexture.m_inTexID);m_pixtexture.m_inTexID=NULL;
  outlet_free(m_pixtexture.m_out1);  m_pixtexture.m_out1=NULL;
}

/////////////////////////////////////////////////////////
// Destructor
//
/////////////////////////////////////////////////////////
pix_movie :: ~pix_movie()
{
  // Clean up the movie
  closeMess();
}

/////////////////////////////////////////////////////////
// render
//
/////////////////////////////////////////////////////////
void pix_movie :: render(GemState *state)
{
  int frame=-1;

 /* get the current frame from the file */

  if (!state || !m_handle)return;

  // get the frame from the decoding-object: film[].cpp
#ifdef HAVE_PTHREADS
  if(m_thread_running) {
    pthread_mutex_lock(m_mutex);
    state->image=m_frame;
  } else
#endif /* PTHREADS */
    state->image=m_handle->getFrame();

  frame=(int)m_reqFrame;
  if (state->image==0){
    outlet_float(m_outEnd,(m_numFrames>0 && (int)m_reqFrame<0)?(m_numFrames-1):0);

    if(frame!=(int)m_reqFrame){
      // someone responded immediately to the outlet_float and changed the requested frame
      // so try to get the newly requested frame:
      if(m_thread_running){
	/* the grabbing-thread is currently locked
	 * we do the grabbing ourselfes
	 */
	m_handle->changeImage((int)m_reqFrame, m_reqTrack);
      }
      state->image=m_handle->getFrame();
    }
  }

  // render using the pix_texture-object
  m_pixtexture.render(state);
}
/////////////////////////////////////////////////////////
// postrender
//
/////////////////////////////////////////////////////////
void pix_movie :: postrender(GemState *state)
{
  if(!m_handle)return;
  if (state && state->image)state->image->newimage = 0;

#ifdef HAVE_PTHREADS
  if(m_thread_running){
    pthread_mutex_unlock(m_mutex);
  }
#endif /* PTHREADS */

  // automatic proceeding
  if (m_auto!=0){
    if(m_thread_running){
      m_reqFrame+=m_auto;
    } else
    if (m_handle->changeImage((int)(m_reqFrame+=m_auto))==FILM_ERROR_FAILURE){
      //      m_reqFrame = m_numFrames;
      outlet_bang(m_outEnd);
    }
  }

  m_pixtexture.postrender(state);
}


/////////////////////////////////////////////////////////
// startRendering
//
/////////////////////////////////////////////////////////
void pix_movie :: startRendering()
{
  m_pixtexture.startRendering();
}

/////////////////////////////////////////////////////////
// stopRendering
//
/////////////////////////////////////////////////////////
void pix_movie :: stopRendering()
{
  m_pixtexture.stopRendering();
}
/////////////////////////////////////////////////////////
// static member function
//
/////////////////////////////////////////////////////////
void pix_movie :: obj_setupCallback(t_class *classPtr)
{
  class_addmethod(classPtr, (t_method)&pix_movie::textureMessCallback,
		  gensym("quality"), A_FLOAT, A_NULL);
  class_addmethod(classPtr, (t_method)&pix_movie::repeatMessCallback,
		  gensym("repeat"), A_FLOAT, A_NULL);
  class_addmethod(classPtr, (t_method)&pix_movie::modeCallback,
		  gensym("mode"), A_FLOAT, A_NULL);
}

void pix_movie :: textureMessCallback(void *data, t_floatarg quality)
{
  GetMyClass(data)->textureQuality((int)quality);
}
void pix_movie :: repeatMessCallback(void *data, t_floatarg quality)
{
  GetMyClass(data)->repeatMess((int)quality);
}

void pix_movie :: modeCallback(void *data, t_floatarg quality)
{
  GetMyClass(data)->modeMess((int)quality);
}
#endif /*__APPLE__*/