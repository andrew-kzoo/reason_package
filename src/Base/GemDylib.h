/*-----------------------------------------------------------------
LOG
    GEM - Graphics Environment for Multimedia

	- registers a loader with Pd

    Copyright (c) 1997-1999 Mark Danks. mark@danks.org
    Copyright (c) G-b�nther Geiger. geiger@epy.co.at-A
    Copyright (c) 2001-2002 IOhannes m zmoelnig. forum::f-b�r::uml�ute. IEM. zmoelnig@iem.kug.ac.at-A
    For information on usage and redistribution, and for a DISCLAIMER OF ALL
    WARRANTIES, see the file, "GEM.LICENSE.TERMS" in this distribution.

-----------------------------------------------------------------*/

#ifndef INCLUDE_GEMDYLIB_H_
#define INCLUDE_GEMDYLIB_H_

#include "Base/GemException.h"


/* an opaque handle to the platform specific library handle */
class GemDylibHandle;
class CPPExtern;

class GemDylib {
 private:
  GemDylibHandle*m_handle;
  static GemDylibHandle*open(const CPPExtern*obj, const char*filename, const char*extension);

 public:
  GemDylib(const CPPExtern*obj, const char*libname, const char*extension=0) throw(GemException);
  GemDylib(const char*libname, const char*extension=0) throw(GemException);
  ~GemDylib(void);


  // if void<procname>(void) exists in dylib, run it and return "true"
  // else return false;
  bool run(const char*procname);

  // if <procname> exists in dylib, return it, else return NULL
  void*proc(const char*procname);

  public:
  /**
   * LoadLib(): convenience function that searches a library named <baselibname> and then runs <procname>()
   * if "extension" is NULL, a plaform-specific default is used
   * on success "true" is returned, else "false
   */
  static bool LoadLib(const char*procname, const char*baselibname, const char*fileext=0);
};




#endif
